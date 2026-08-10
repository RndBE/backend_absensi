<?php

namespace App\Jobs;

use App\Models\LeaveRequest;
use App\Support\DailyLeaveSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Kirim satu pengajuan cuti/sakit ke DailyCloseApp, atau hapus dari sana.
 *
 * Job ini menerima id-nya saja, lalu membaca ulang kondisi terkini dan MENENTUKAN
 * SENDIRI arah aksinya. Jadi satu job untuk dua transisi, dan hasilnya self-correcting:
 * kalau saat job jalan status ternyata sudah berubah lagi, yang dikirim adalah kondisi
 * yang benar sekarang — bukan kondisi saat job dibuat.
 *
 * Sifat itu juga yang dipakai command daily:sync-leaves untuk menambal entri basi.
 *
 * Kontrak: docs/api-internal-sinkron-cuti.md di repo DailyCloseApp.
 */
class SyncLeaveToDailyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $leaveRequestId)
    {
    }

    /**
     * Naik bertahap sampai sejam: kalau Daily sedang dideploy, percobaan berikutnya
     * sebaiknya tidak jatuh di menit yang sama.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        if (! $this->isConfigured()) {
            Log::warning('Sinkron cuti ke Daily dilewati: DAILY_APP_URL / DAILY_INTERNAL_SECRET belum lengkap.', [
                'leave_request_id' => $this->leaveRequestId,
            ]);

            return;
        }

        $leave = LeaveRequest::with(['leaveType', 'employee:id,email,full_name'])
            ->find($this->leaveRequestId);

        // Pengajuannya hilang dari HRIS: tipenya tidak bisa lagi diperiksa, jadi
        // pastikan saja tidak ada sisa di Daily. DELETE-nya idempotent.
        if (! $leave) {
            $this->revoke();

            return;
        }

        $type = DailyLeaveSync::typeFor($leave);

        // Izin parsial, WFH, atau jenis baru yang belum dipetakan: tidak pernah dikirim,
        // jadi tidak ada yang perlu dihapus juga. Jenis baru dicatat supaya ketahuan.
        if (! $type) {
            if (DailyLeaveSync::isUnmapped($leave)) {
                Log::info('Jenis izin belum dipetakan ke Daily, sengaja tidak dikirim.', [
                    'leave_request_id' => $leave->id,
                    'leave_type' => $leave->leaveType?->name,
                ]);
            }

            return;
        }

        if ($leave->status !== 'approved') {
            $this->revoke();

            return;
        }

        $email = $leave->employee?->email;

        if (! filled($email)) {
            Log::warning('Cuti tidak bisa disinkronkan ke Daily: pegawai tidak punya email.', [
                'leave_request_id' => $leave->id,
                'employee_id' => $leave->employee_id,
            ]);

            return;
        }

        $this->push($leave, $type, $email);
    }

    private function push(LeaveRequest $leave, string $type, string $email): void
    {
        $response = $this->http()->post($this->endpoint('/api/internal/leaves/sync'), [
            'external_id' => (string) $leave->id,
            'email' => $email,
            'type' => $type,
            'start_date' => $leave->start_date->toDateString(),
            'end_date' => $leave->end_date->toDateString(),
            'reason' => $this->reasonFor($leave),
        ]);

        if ($response->successful()) {
            $overlapping = (array) $response->json('overlapping_manual_ids', []);

            if ($overlapping !== []) {
                // Catatan manual karyawan yang beririsan. Sengaja tidak dihapus — itu
                // data milik karyawan. Dicatat supaya HRD bisa merapikan kalau perlu.
                Log::info('Sinkron cuti ke Daily beririsan dengan catatan manual karyawan.', [
                    'leave_request_id' => $leave->id,
                    'overlapping_manual_ids' => $overlapping,
                ]);
            }

            return;
        }

        $this->handleFailure($response, 'mencatat', $leave->id);
    }

    /**
     * Keterangan yang ikut dikirim, dipotong ke batas kontrak (500 karakter) supaya
     * alasan panjang tidak berbalas 422. Kosong dikirim sebagai null, bukan string
     * kosong, karena di Daily field-nya nullable.
     *
     * Catatan: ini teks bebas yang diisi karyawan, jadi untuk izin sakit isinya bisa
     * memuat keterangan medis. Pengirimannya keputusan sadar, bukan kelalaian.
     */
    private function reasonFor(LeaveRequest $leave): ?string
    {
        $reason = trim((string) $leave->reason);

        if ($reason === '') {
            return null;
        }

        return Str::limit($reason, 500, '');
    }

    private function revoke(): void
    {
        $response = $this->http()->delete(
            $this->endpoint('/api/internal/leaves/'.$this->leaveRequestId)
        );

        if ($response->successful()) {
            return;
        }

        $this->handleFailure($response, 'menghapus', $this->leaveRequestId);
    }

    /**
     * Kegagalan yang tidak akan membaik kalau diulang (payload salah, secret salah,
     * pegawai tidak punya akun Daily) dicatat lalu diselesaikan. Sisanya dilempar
     * supaya queue mencoba lagi.
     */
    private function handleFailure(Response $response, string $action, int $leaveRequestId): void
    {
        $status = $response->status();

        $context = [
            'leave_request_id' => $leaveRequestId,
            'status' => $status,
            'body' => $response->json() ?? $response->body(),
        ];

        if ($status === 404) {
            // Wajar: tidak semua pegawai HRIS punya akun Daily. Bukan kegagalan sistem.
            Log::warning("Gagal {$action} cuti di Daily: akun Daily tidak ditemukan atau nonaktif.", $context);

            return;
        }

        if ($status === 422) {
            Log::error("Gagal {$action} cuti di Daily: payload ditolak. Biasanya jenis izin baru yang belum dipetakan.", $context);

            return;
        }

        if ($status === 403) {
            Log::error("Gagal {$action} cuti di Daily: secret bridge ditolak. Cek DAILY_INTERNAL_SECRET.", $context);

            return;
        }

        // 5xx, timeout, dan sisanya: kemungkinan sementara, biarkan queue mengulang.
        throw new RuntimeException(
            "Gagal {$action} cuti di Daily (HTTP {$status}) untuk pengajuan #{$leaveRequestId}."
        );
    }

    private function http(): PendingRequest
    {
        $client = Http::withHeaders([
            'X-Internal-Secret' => (string) config('services.daily.internal_secret'),
        ])
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10);

        if (! config('services.daily.verify_ssl', true)) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.daily.url'), '/').$path;
    }

    private function isConfigured(): bool
    {
        return filled(config('services.daily.url'))
            && filled(config('services.daily.internal_secret'));
    }
}

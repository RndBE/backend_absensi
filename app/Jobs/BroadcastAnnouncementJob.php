<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sebarkan satu pengumuman menjadi notifikasi in-app per karyawan.
 *
 * Tabel `notifications` bersifat per penerima (`employee_id` wajib), jadi tidak ada bentuk
 * "satu baris untuk semua orang" — satu pengumuman ke 200 karyawan berarti 200 baris.
 * Karena itu penyisipannya dipotong per 500 baris, bukan satu per satu lewat Eloquent.
 *
 * Job ini menerima id-nya saja dan membaca ulang kondisi terkini: kalau pengumumannya
 * sudah dihapus sebelum antrean sempat jalan, job berhenti tanpa mengirim apa-apa.
 *
 * Sengaja LEWAT ANTREAN, bukan penjadwal. Di server Plesk hanya `queue:work` yang hidup;
 * `Schedule::command` tidak dijalankan sama sekali, jadi apa pun yang bergantung pada
 * penjadwal tidak akan pernah menyala.
 */
class BroadcastAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $announcementId)
    {
    }

    public function handle(): void
    {
        $pengumuman = Announcement::find($this->announcementId);

        if (! $pengumuman) {
            Log::info('Pengumuman sudah tidak ada saat antrean berjalan, penyebaran dilewati.', [
                'announcement_id' => $this->announcementId,
            ]);

            return;
        }

        $penerima = Employee::query()
            ->select('id')
            ->where('company_id', $pengumuman->company_id)
            ->where('is_active', true)
            ->when($pengumuman->department_id, fn ($q) => $q->where('department_id', $pengumuman->department_id))
            ->pluck('id');

        if ($penerima->isEmpty()) {
            return;
        }

        $sekarang = now();
        $jumlah = 0;

        foreach ($penerima->chunk(500) as $bagian) {
            $baris = $bagian->map(fn (int $employeeId) => [
                'employee_id' => $employeeId,
                'title' => $pengumuman->title,
                // Notifikasi cuma pengantar; isi lengkapnya dibaca di Timeline.
                'message' => Str::limit($pengumuman->body, 200),
                'type' => 'announcement',
                'reference_type' => Announcement::class,
                'reference_id' => $pengumuman->id,
                'is_read' => false,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])->all();

            Notification::insert($baris);
            $jumlah += count($baris);
        }

        Log::info('Pengumuman disebarkan.', [
            'announcement_id' => $pengumuman->id,
            'penerima' => $jumlah,
        ]);
    }
}

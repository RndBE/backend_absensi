<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien API inventory (BE-INVENTORY).
 *
 * Inventory tetap satu-satunya pemilik data aset — HRIS memanggil endpoint ini
 * setiap halaman detail karyawan dibuka dan tidak menyimpan salinannya, supaya
 * tidak ada data basi.
 *
 * Dokumentasi endpoint: be-inventory/docs/api-hris-aset-karyawan.md
 */
class InventoryClient
{
    private ?string $baseUrl;
    private ?string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $url = trim((string) config('services.inventory.url'));

        $this->baseUrl = $url !== '' ? rtrim($url, '/') : null;
        $this->apiKey  = config('services.inventory.api_key') ?: null;
        $this->timeout = (int) config('services.inventory.timeout', 5);
    }

    /**
     * Daftar aset yang sedang dipegang karyawan.
     *
     * Tidak pernah melempar exception — halaman detail karyawan harus tetap
     * terbuka walau inventory sedang mati atau lambat.
     *
     * Sengaja tanpa retry: ini dipanggil saat render halaman, jadi waktu tunggu
     * harus terbatas. Satu percobaan = maksimal $timeout detik.
     *
     * Hasilnya dipisah dua karena artinya beda: `loans` itu pinjaman sementara yang
     * wajib dikembalikan, `pic` itu penugasan tetap. Menggabung keduanya jadi satu
     * daftar bikin HR salah baca kewajiban karyawan saat resign.
     *
     * Nilai `status`:
     *   ok           panggilan berhasil; kedua daftar boleh kosong (memang tidak pegang aset)
     *   no_email     karyawan belum punya email, jadi tidak bisa dicocokkan
     *   not_linked   emailnya tidak terdaftar di inventory (404) — beda dengan "tidak punya aset"
     *   disabled     integrasi belum dikonfigurasi di .env
     *   unavailable  inventory error / tidak terjangkau / token ditolak
     *
     * @return array{status: string, loans: array, pic: array, message: ?string}
     */
    public function employeeAssets(?string $email): array
    {
        if (!$this->baseUrl || !$this->apiKey) {
            return $this->result('disabled', 'Integrasi inventory belum dikonfigurasi.');
        }

        $email = trim((string) $email);
        if ($email === '') {
            return $this->result('no_email', 'Karyawan belum punya email, aset tidak bisa dicocokkan.');
        }

        try {
            $response = Http::withHeaders(['X-API-KEY' => $this->apiKey])
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/api/hris/aset-karyawan", ['email' => $email]);

            // 404 hanya berarti "email tidak terdaftar" kalau bodynya memang balasan
            // inventory. 404 polos biasanya URL salah arah (mis. INVENTORY_URL nunjuk
            // ke aplikasi lain) — jangan sampai itu tampil sebagai masalah data karyawan.
            if ($response->status() === 404) {
                if ($response->json('success') === false) {
                    return $this->result('not_linked', 'Karyawan belum terhubung ke sistem inventory.');
                }

                Log::warning('[Inventory] 404 tak dikenal — cek INVENTORY_URL: ' . $response->body());
                return $this->result('unavailable', 'Data aset gagal dimuat.');
            }

            if ($response->status() === 401) {
                Log::warning('[Inventory] Token ditolak — INVENTORY_API_KEY beda dengan sisi inventory.');
                return $this->result('unavailable', 'Data aset gagal dimuat.');
            }

            if ($response->failed()) {
                Log::warning('[Inventory] Gagal ambil aset karyawan: ' . $response->status() . ' ' . $response->body());
                return $this->result('unavailable', 'Data aset gagal dimuat.');
            }

            $loans = [];
            $pic   = [];

            foreach ($response->json('data') ?: [] as $row) {
                // Inventory versi lama belum mengirim `sumber`. Anggap peminjaman
                // supaya HRIS tetap benar kalau servernya belum ikut diperbarui.
                if (data_get($row, 'sumber') === 'PIC') {
                    $pic[] = $row;
                } else {
                    $loans[] = $row;
                }
            }

            return [
                'status'  => 'ok',
                'loans'   => $loans,
                'pic'     => $pic,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('[Inventory] Tidak terjangkau: ' . $e->getMessage());
            return $this->result('unavailable', 'Data aset gagal dimuat.');
        }
    }

    /**
     * @return array{status: string, loans: array, pic: array, message: ?string}
     */
    private function result(string $status, ?string $message): array
    {
        return ['status' => $status, 'loans' => [], 'pic' => [], 'message' => $message];
    }
}

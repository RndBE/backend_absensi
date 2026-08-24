<?php

namespace App\Support;

use App\Jobs\BroadcastAnnouncementJob;
use App\Models\Announcement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Aturan penerbitan pengumuman.
 *
 * Ditarik keluar dari controller supaya aturan yang gampang hilang punya satu alamat:
 * keputusan kapan notifikasi disebar, dan pembersihan berkas lama saat lampiran diganti.
 */
class AnnouncementPublisher
{
    /**
     * @param  array{title: string, body: string, published_at?: ?string}  $data
     */
    public static function terbitkan(array $data, int $companyId, ?int $penulisId, bool $dipaku, array $lampiran = []): Announcement
    {
        $berlakuMulai = self::berlakuMulai($data);

        $pengumuman = Announcement::create(array_merge([
            'company_id' => $companyId,
            'department_id' => null,
            'title' => $data['title'],
            'body' => $data['body'],
            'published_at' => $berlakuMulai,
            'expires_at' => null,
            'is_pinned' => $dipaku,
            'is_visible' => true,
            'created_by' => $penulisId,
            'link_url' => $data['link_url'] ?? null,
        ], self::simpanLampiran($lampiran)));

        self::sebarkan($pengumuman);

        return $pengumuman;
    }

    /**
     * Kirim notifikasi in-app, dengan penundaan bila tanggal berlakunya masih di depan.
     *
     * Munculnya pengumuman di Timeline tidak butuh penjadwal — scope `tayang()` menyaring
     * saat dibaca. Yang butuh perlakuan khusus hanya notifikasinya: tanpa penundaan, orang
     * dikabari hari ini soal pengumuman yang baru tampil pekan depan.
     *
     * Penundaan antrean butuh QUEUE_CONNECTION selain `sync`. Dengan `sync`, `delay()`
     * diabaikan dan job berjalan seketika — karena itu di keadaan itu notifikasinya
     * DILEWATI, bukan dikirim di waktu yang salah.
     */
    private static function sebarkan(Announcement $pengumuman): void
    {
        if (! $pengumuman->belumBerlaku()) {
            BroadcastAnnouncementJob::dispatch($pengumuman->id);

            return;
        }

        if (config('queue.default') === 'sync') {
            Log::info('Notifikasi pengumuman terjadwal dilewati: antrean masih sync sehingga penundaan tidak berlaku.', [
                'announcement_id' => $pengumuman->id,
                'published_at' => $pengumuman->published_at->toDateTimeString(),
            ]);

            return;
        }

        BroadcastAnnouncementJob::dispatch($pengumuman->id)->delay($pengumuman->published_at);
    }

    /**
     * Menyunting sengaja TIDAK menyebar ulang notifikasi. Membetulkan salah ketik tidak
     * boleh membangunkan seluruh kantor untuk kedua kalinya.
     *
     * @param  array{title: string, body: string, published_at?: ?string}  $data
     */
    public static function ubah(Announcement $pengumuman, array $data, bool $dipaku, array $lampiran = [], array $hapus = [], bool $tampil = true): Announcement
    {
        $baru = self::simpanLampiran($lampiran);

        // Berkas lama dibuang dari disk begitu tergantikan atau dilepas. Tanpa ini setiap
        // penyuntingan meninggalkan berkas yatim yang tidak pernah dibersihkan siapa pun.
        if (isset($baru['image_path']) || in_array('image', $hapus, true)) {
            self::hapusBerkas($pengumuman->image_path);
        }

        if (isset($baru['file_path']) || in_array('file', $hapus, true)) {
            self::hapusBerkas($pengumuman->file_path);
        }

        $atribut = array_merge([
            'title' => $data['title'],
            'body' => $data['body'],
            'published_at' => self::berlakuMulai($data, $pengumuman->published_at),
            // Baris lama yang terlanjur bertanggal kedaluwarsa dinormalkan begitu disunting.
            // Sejak saklar tampil/sembunyi ada, kedaluwarsa tidak lagi punya peran.
            'expires_at' => null,
            'is_pinned' => $dipaku,
            'is_visible' => $tampil,
            'link_url' => $data['link_url'] ?? null,
        ], $baru);

        // Melepas lampiran hanya berlaku bila tidak ada gantinya di kiriman yang sama.
        if (in_array('image', $hapus, true) && ! isset($baru['image_path'])) {
            $atribut['image_path'] = null;
        }

        if (in_array('file', $hapus, true) && ! isset($baru['file_path'])) {
            $atribut = array_merge($atribut, [
                'file_path' => null, 'file_name' => null, 'file_size' => null, 'file_mime' => null,
            ]);
        }

        $pengumuman->update($atribut);

        return $pengumuman;
    }

    /**
     * Buang berkas milik pengumuman yang dihapus. Harus dipanggil SEBELUM barisnya dihapus,
     * karena sesudahnya jejak nama berkasnya sudah tidak ada.
     */
    public static function hapusSemuaBerkas(Announcement $pengumuman): void
    {
        self::hapusBerkas($pengumuman->image_path);
        self::hapusBerkas($pengumuman->file_path);
    }

    /**
     * Simpan foto dan berkas yang diunggah.
     *
     * Disk `local` (privat), bukan `public`: pengumuman internal tidak boleh terbuka bagi
     * siapa pun yang menebak URL-nya. Penyajiannya lewat rute yang memeriksa perusahaan
     * si pembaca.
     *
     * @param  array{image?: ?UploadedFile, file?: ?UploadedFile}  $lampiran
     * @return array<string, mixed>
     */
    private static function simpanLampiran(array $lampiran): array
    {
        $hasil = [];

        if (($lampiran['image'] ?? null) instanceof UploadedFile) {
            $hasil['image_path'] = $lampiran['image']->store('announcements/images', 'local');
        }

        if (($lampiran['file'] ?? null) instanceof UploadedFile) {
            $berkas = $lampiran['file'];
            $hasil['file_path'] = $berkas->store('announcements/files', 'local');
            // Nama asli disimpan terpisah supaya unduhan memakai nama yang dikenali
            // pembaca, bukan nama acak hasil penyimpanan.
            $hasil['file_name'] = $berkas->getClientOriginalName();
            $hasil['file_size'] = $berkas->getSize();
            $hasil['file_mime'] = $berkas->getClientMimeType();
        }

        return $hasil;
    }

    private static function hapusBerkas(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Kapan pengumuman mulai berlaku. Kosong berarti sekarang juga.
     *
     * Diambil dari AWAL hari, bukan tengah hari: pengumuman bertanggal 1 September harus
     * sudah tampil sejak dini hari tanggal itu, bukan baru pada jam saat HR mengetiknya.
     *
     * @param  array{published_at?: ?string}  $data
     */
    public static function berlakuMulai(array $data, ?Carbon $bawaan = null): Carbon
    {
        return ! empty($data['published_at'])
            ? Carbon::parse($data['published_at'])->startOfDay()
            : ($bawaan ?? Carbon::now());
    }

    /**
     * @return array{title: string, body: string, published_at: ?string}
     */
    public static function aturanValidasi(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'published_at' => ['nullable', 'date'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            // Jenis dibatasi daftar putih, bukan sekadar `image`/`file`. Pengumuman dibaca
            // seluruh kantor; jangan biarkan berkas jenis sembarang beredar lewat sini.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function namaAtribut(): array
    {
        return [
            'title' => 'judul',
            'body' => 'isi pengumuman',
            'published_at' => 'tanggal berlaku',
            'link_url' => 'tautan',
            'image' => 'foto',
            'file' => 'berkas',
        ];
    }
}

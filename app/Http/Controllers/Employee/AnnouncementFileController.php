<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan foto dan berkas lampiran pengumuman ke karyawan.
 *
 * Berkasnya ada di disk `local` yang tidak bisa diakses dari luar, jadi harus lewat sini.
 * Itu disengaja: pengumuman internal tidak boleh terbuka bagi siapa pun yang menebak URL.
 *
 * Dua penjagaan di setiap permintaan:
 * - Perusahaan pembaca harus sama dengan perusahaan pengumuman.
 * - Pengumumannya harus sedang tayang. Pengumuman yang sudah kedaluwarsa tidak lagi
 *   terlihat di Timeline, jadi lampirannya pun tidak boleh masih bisa diambil.
 */
class AnnouncementFileController extends Controller
{
    public function image(Request $request, int $id): StreamedResponse
    {
        $pengumuman = $this->cariTayang($request, $id);

        abort_unless($pengumuman->image_path && Storage::disk('local')->exists($pengumuman->image_path), 404);

        // Inline, bukan unduhan: fotonya memang untuk ditampilkan di dalam kartu Timeline.
        return Storage::disk('local')->response($pengumuman->image_path);
    }

    public function file(Request $request, int $id): StreamedResponse
    {
        $pengumuman = $this->cariTayang($request, $id);

        abort_unless($pengumuman->file_path && Storage::disk('local')->exists($pengumuman->file_path), 404);

        return Storage::disk('local')->download(
            $pengumuman->file_path,
            $pengumuman->file_name ?: 'lampiran'
        );
    }

    private function cariTayang(Request $request, int $id): Announcement
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return Announcement::query()
            ->tayang()
            ->where('company_id', $employee->company_id)
            ->findOrFail($id);
    }
}

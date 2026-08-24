<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Company;
use App\Models\Employee;
use App\Support\AnnouncementPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Pengelolaan pengumuman HR yang tampil di Timeline dashboard karyawan.
 *
 * Izinnya menumpang `company.manage` lewat pola rute `admin.company.*` di
 * config/admin_permissions.php — siapa yang boleh mengelola info perusahaan, boleh
 * mengumumkan. Itu sebabnya rutenya bernama `admin.company.announcements.*`.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->companyId();

        return view('admin.announcements.index', [
            'announcements' => Announcement::with('penulis:id,full_name')
                ->where('company_id', $companyId)
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->paginate(15),
            'now' => Carbon::now(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validasi($request);

        AnnouncementPublisher::terbitkan(
            $validated,
            $this->companyId(),
            session('admin_id'),
            $request->boolean('is_pinned'),
            $this->lampiran($request)
        );

        return redirect()->route('admin.company.announcements.index')
            ->with('success', 'Pengumuman diterbitkan dan sedang dikirim ke karyawan.');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->pastikanMilikPerusahaan($announcement);
        $validated = $this->validasi($request);

        AnnouncementPublisher::ubah(
            $announcement,
            $validated,
            $request->boolean('is_pinned'),
            $this->lampiran($request),
            // Kotak centang "lepas" hanya dibaca sebagai perintah bila memang dicentang;
            // yang tidak dicentang harus meninggalkan lampiran lama apa adanya.
            array_keys(array_filter([
                'image' => $request->boolean('hapus_image'),
                'file' => $request->boolean('hapus_file'),
            ])),
            $request->boolean('is_visible')
        );

        return redirect()->route('admin.company.announcements.index')
            ->with('success', 'Pengumuman diperbarui.');
    }

    public function destroy(Announcement $announcement)
    {
        $this->pastikanMilikPerusahaan($announcement);
        // Berkasnya dibuang lebih dulu; sesudah barisnya hilang, jejak nama berkasnya
        // ikut hilang dan tak ada lagi yang tahu apa yang harus dihapus dari disk.
        AnnouncementPublisher::hapusSemuaBerkas($announcement);
        $announcement->delete();

        return redirect()->route('admin.company.announcements.index')
            ->with('success', 'Pengumuman dihapus.');
    }

    /**
     * @return array{title: string, body: string, expires_at: ?string}
     */
    private function validasi(Request $request): array
    {
        return $request->validate(
            AnnouncementPublisher::aturanValidasi(),
            [],
            AnnouncementPublisher::namaAtribut()
        );
    }

    /**
     * Sajikan foto lampiran untuk pratinjau di daftar.
     *
     * Perlu rute tersendiri walau sudah ada padanannya di portal karyawan: berkasnya di disk
     * privat, dan rute portal berjaga pada sesi karyawan — sesi admin tidak melewatinya.
     * Bedanya juga di syarat: di sini pengumuman kedaluwarsa tetap boleh dilihat, karena HR
     * memang perlu memeriksa apa yang dulu diterbitkan.
     */
    public function image(Announcement $announcement)
    {
        $this->pastikanMilikPerusahaan($announcement);

        abort_unless(
            $announcement->image_path && Storage::disk('local')->exists($announcement->image_path),
            404
        );

        return Storage::disk('local')->response($announcement->image_path);
    }

    /**
     * @return array{image: ?\Illuminate\Http\UploadedFile, file: ?\Illuminate\Http\UploadedFile}
     */
    private function lampiran(Request $request): array
    {
        return [
            'image' => $request->file('image'),
            'file' => $request->file('file'),
        ];
    }

    private function pastikanMilikPerusahaan(Announcement $announcement): void
    {
        abort_if($announcement->company_id !== $this->companyId(), 403, 'Pengumuman ini bukan milik perusahaan Anda.');
    }

    private function companyId(): int
    {
        $admin = Employee::find(session('admin_id'));

        return (int) ($admin?->company_id ?: Company::query()->value('id') ?: 1);
    }
}

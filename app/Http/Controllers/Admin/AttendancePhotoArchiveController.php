<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendancePhotoArchive;
use App\Models\Employee;
use App\Services\AttendancePhotoArchiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendancePhotoArchiveController extends Controller
{
    public function index(Request $request, AttendancePhotoArchiveService $service)
    {
        $admin = Employee::findOrFail(session('admin_id'));
        $period = $request->period ?: now()->subMonthNoOverflow()->format('Y-m');

        $archives = AttendancePhotoArchive::query()
            ->where('company_id', $admin->company_id)
            ->with(['generator:id,full_name', 'archiver:id,full_name'])
            ->latest('period')
            ->latest()
            ->get();

        $selectedArchive = $archives->firstWhere('period', $period);
        $photoCount = $service->countPhotos($admin->company_id, $period);

        return view('admin.attendance-photo-archives.index', compact(
            'period',
            'archives',
            'selectedArchive',
            'photoCount'
        ));
    }

    public function generate(Request $request, AttendancePhotoArchiveService $service)
    {
        $request->validate([
            'period' => 'required|date_format:Y-m',
        ]);

        $admin = Employee::findOrFail(session('admin_id'));

        try {
            $archive = $service->generate($admin->company_id, $request->period, $admin->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal membuat ZIP arsip foto: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.attendance-photo-archives.index', ['period' => $archive->period])
            ->with('success', 'ZIP arsip foto absensi berhasil dibuat.');
    }

    public function download(AttendancePhotoArchive $archive)
    {
        $admin = Employee::findOrFail(session('admin_id'));
        $this->ensureCompanyArchive($archive, $admin->company_id);

        if (!$archive->zip_file_path || !Storage::disk('local')->exists($archive->zip_file_path)) {
            return back()->with('error', 'File ZIP tidak tersedia. Jika sudah diupload, gunakan link Drive arsip.');
        }

        return response()->download(
            Storage::disk('local')->path($archive->zip_file_path),
            $archive->zip_file_name ?: "foto_absensi_{$archive->period}.zip"
        );
    }

    public function markUploaded(Request $request, AttendancePhotoArchive $archive, AttendancePhotoArchiveService $service)
    {
        $request->validate([
            'drive_link' => 'required|url|max:2000',
        ], [
            'drive_link.required' => 'Link Google Drive ZIP wajib diisi.',
            'drive_link.url' => 'Link Google Drive ZIP harus berupa URL valid.',
        ]);

        $admin = Employee::findOrFail(session('admin_id'));
        $this->ensureCompanyArchive($archive, $admin->company_id);

        try {
            $service->markUploaded($archive, $request->drive_link, $admin->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menandai arsip: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.attendance-photo-archives.index', ['period' => $archive->period])
            ->with('success', 'Arsip ditandai sudah upload dan foto lokal berhasil dihapus.');
    }

    private function ensureCompanyArchive(AttendancePhotoArchive $archive, int $companyId): void
    {
        abort_unless($archive->company_id === $companyId, 404);
    }
}

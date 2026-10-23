<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRegulation;
use App\Models\CompanyRegulationAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Info Perusahaan untuk app mobile: identitas perusahaan dan peraturan yang sedang berlaku,
 * sama dengan halaman Info Perusahaan di portal employee (Employee\CompanyInfoController).
 *
 * Lampiran peraturan dibuka di browser HP yang tidak membawa token API. Karena itu app meminta
 * tautan bertanda tangan yang berlaku FILE_LINK_MINUTES menit tepat saat lampiran diketuk,
 * bukan menyimpannya di daftar yang bisa basi.
 */
class CompanyInfoController extends Controller
{
    public const FILE_LINK_MINUTES = 30;

    public function index(Request $request): JsonResponse
    {
        $employee = $request->user();
        $company = $employee->company ?: Company::find($employee->company_id);

        $regulations = CompanyRegulation::query()
            ->with('attachments')
            ->where('company_id', $employee->company_id)
            ->active()
            ->orderByDesc('effective_date')
            ->orderBy('title')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                    'logo' => $company->logo ? asset('storage/'.$company->logo) : null,
                    'address' => $company->address,
                    'phone' => $company->phone,
                    'email' => $company->email,
                ] : null,
                'regulations' => $regulations->map(fn (CompanyRegulation $regulation) => [
                    'id' => $regulation->id,
                    'title' => $regulation->title,
                    'effective_date' => $regulation->effective_date?->toDateString(),
                    'points' => self::points($regulation->content),
                    'attachments' => $regulation->attachments->map(fn (CompanyRegulationAttachment $attachment) => [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_size' => $attachment->file_size,
                        'file_mime' => $attachment->file_mime,
                    ])->values(),
                ])->values(),
            ],
        ]);
    }

    /** Tautan segar untuk satu lampiran peraturan milik perusahaan karyawan yang masih aktif. */
    public function attachmentLink(Request $request, int $regulation, int $attachment): JsonResponse
    {
        $employee = $request->user();

        $found = CompanyRegulation::query()
            ->where('company_id', $employee->company_id)
            ->active()
            ->find($regulation);
        $file = $found?->attachments()->find($attachment);

        if (! $file || ! Storage::disk('local')->exists($file->file_path)) {
            return response()->json(['success' => false, 'message' => 'Lampiran tidak ditemukan.'], 404);
        }

        // Tanda tangan dihitung dari path saja (relative) supaya tetap sah di balik proxy yang
        // mengubah skema/host; host diambil dari request yang sedang berjalan.
        $url = url(URL::temporarySignedRoute(
            'company-regulations.link.attachment',
            now()->addMinutes(self::FILE_LINK_MINUTES),
            ['regulation' => $found->id, 'attachment' => $file->id],
            false,
        ));

        return response()->json([
            'success' => true,
            'data' => ['url' => $url, 'file_name' => $file->file_name, 'expires_in_minutes' => self::FILE_LINK_MINUTES],
        ]);
    }

    /** Lampiran peraturan, dibuka dari tautan bertanda tangan. */
    public function attachmentFromLink(int $regulation, int $attachment)
    {
        $found = CompanyRegulation::query()->active()->findOrFail($regulation);
        $file = $found->attachments()->findOrFail($attachment);
        abort_unless(Storage::disk('local')->exists($file->file_path), 404);

        return Storage::disk('local')->response($file->file_path, $file->file_name);
    }

    /**
     * Isi peraturan ditulis satu butir per baris; baris kosong dibuang, sama seperti di portal.
     *
     * @return list<string>
     */
    private static function points(?string $content): array
    {
        return collect(preg_split('/\r\n|\r|\n/', trim((string) $content)))
            ->map(fn ($point) => trim($point))
            ->filter()
            ->values()
            ->all();
    }
}

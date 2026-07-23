<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRegulation;
use App\Models\Employee;
use App\Support\SimpleSpreadsheetReader;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyRegulationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $this->validateRegulation($request);
        $companyId = $this->companyId($request);

        $regulation = new CompanyRegulation($validated);
        $regulation->company_id = $companyId;
        $regulation->category = null;
        $regulation->is_active = $request->boolean('is_active');

        if ($request->hasFile('attachment')) {
            $this->fillAttachment($regulation, $request);
        }

        $regulation->save();

        return redirect()->route('admin.company.index')
            ->with('success', 'Peraturan perusahaan berhasil ditambahkan.');
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt,xlsx,pdf', 'max:25600'],
        ], [], [
            'import_file' => 'file import',
        ]);

        $file = $validated['import_file'];
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'pdf') {
            return $this->importPdfDocument($request, $file);
        }

        $rows = SimpleSpreadsheetReader::rows($file->getRealPath(), 'Sheet1', $file->getClientOriginalExtension());

        if (count($rows) < 2) {
            return redirect()->route('admin.company.index')
                ->withErrors(['import_file' => 'File import harus berisi header dan minimal satu baris peraturan.'])
                ->withInput();
        }

        $headers = $this->normalizeImportHeaders($rows[0]);
        if (! in_array('title', $headers, true)) {
            return redirect()->route('admin.company.index')
                ->withErrors(['import_file' => 'Kolom judul wajib ada. Gunakan header: judul, isi, tanggal_berlaku, status.'])
                ->withInput();
        }

        $companyId = $this->companyId($request);
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $headers, $companyId, &$created, &$skipped) {
            foreach (array_slice($rows, 1) as $row) {
                if ($this->isBlankImportRow($row)) {
                    continue;
                }

                $payload = $this->mapImportRow($headers, $row);
                $title = trim((string) ($payload['title'] ?? ''));

                if ($title === '') {
                    $skipped++;
                    continue;
                }

                CompanyRegulation::create([
                    'company_id' => $companyId,
                    'title' => mb_substr($title, 0, 255),
                    'category' => null,
                    'content' => trim((string) ($payload['content'] ?? '')) ?: null,
                    'effective_date' => $this->parseImportDate($payload['effective_date'] ?? null),
                    'is_active' => $this->parseImportStatus($payload['status'] ?? null),
                ]);

                $created++;
            }
        });

        if ($created === 0) {
            return redirect()->route('admin.company.index')
                ->withErrors(['import_file' => 'Tidak ada peraturan yang berhasil diimport. Pastikan kolom judul terisi.'])
                ->withInput();
        }

        $message = "Import selesai. {$created} peraturan ditambahkan.";
        if ($skipped > 0) {
            $message .= " {$skipped} baris dilewati karena judul kosong.";
        }

        return redirect()->route('admin.company.index')->with('success', $message);
    }

    public function update(Request $request, CompanyRegulation $regulation)
    {
        $this->authorizeCompanyRegulation($request, $regulation);

        $validated = $this->validateRegulation($request);
        $regulation->fill($validated);
        $regulation->category = null;
        $regulation->is_active = $request->boolean('is_active');

        if ($request->boolean('remove_attachment')) {
            $this->deleteAttachment($regulation);
            $regulation->forceFill([
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'file_mime' => null,
            ]);
        }

        if ($request->hasFile('attachment')) {
            $this->deleteAttachment($regulation);
            $this->fillAttachment($regulation, $request);
        }

        $regulation->save();

        return redirect()->route('admin.company.index')
            ->with('success', 'Peraturan perusahaan berhasil diperbarui.');
    }

    public function destroy(Request $request, CompanyRegulation $regulation)
    {
        $this->authorizeCompanyRegulation($request, $regulation);
        $this->deleteAttachment($regulation);
        $regulation->delete();

        return redirect()->route('admin.company.index')
            ->with('success', 'Peraturan perusahaan berhasil dihapus.');
    }

    public function download(Request $request, CompanyRegulation $regulation)
    {
        $this->authorizeCompanyRegulation($request, $regulation);

        abort_unless($regulation->file_path && Storage::disk('local')->exists($regulation->file_path), 404);

        return Storage::disk('local')->download($regulation->file_path, $regulation->file_name);
    }

    private function validateRegulation(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:10000'],
            'effective_date' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
        ], [], [
            'title' => 'judul',
            'content' => 'isi peraturan',
            'effective_date' => 'tanggal berlaku',
            'attachment' => 'lampiran',
        ]);
    }

    private function importPdfDocument(Request $request, UploadedFile $file)
    {
        $regulation = new CompanyRegulation([
            'title' => $this->titleFromImportedFile($file),
            'category' => null,
            'content' => 'Dokumen peraturan perusahaan resmi tersedia sebagai lampiran PDF.',
            'is_active' => true,
        ]);
        $regulation->company_id = $this->companyId($request);
        $this->fillAttachmentFromFile($regulation, $file);
        $regulation->save();

        return redirect()->route('admin.company.index')
            ->with('success', 'PDF peraturan perusahaan berhasil diimport sebagai dokumen aktif.');
    }

    private function normalizeImportHeaders(array $headerRow): array
    {
        return array_map(function ($header) {
            $key = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim((string) $header)));
            $key = trim((string) $key, '_');

            return match ($key) {
                'judul', 'title', 'nama_peraturan' => 'title',
                'isi', 'isi_peraturan', 'content', 'deskripsi', 'keterangan' => 'content',
                'tanggal_berlaku', 'berlaku', 'effective_date' => 'effective_date',
                'status' => 'status',
                default => $key,
            };
        }, $headerRow);
    }

    private function mapImportRow(array $headers, array $row): array
    {
        $payload = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $payload[$header] = $row[$index] ?? null;
        }

        return $payload;
    }

    private function isBlankImportRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseImportStatus($value): bool
    {
        $status = mb_strtolower(trim((string) $value));

        if ($status === '') {
            return true;
        }

        return ! in_array($status, ['0', 'draft', 'draf', 'nonaktif', 'tidak aktif', 'inactive', 'false', 'no', 'tidak'], true);
    }

    private function parseImportDate($value): ?string
    {
        $date = trim((string) $value);

        if ($date === '') {
            return null;
        }

        if (is_numeric($date)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $date)->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $date)->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function titleFromImportedFile(UploadedFile $file): string
    {
        $title = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $title = trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $title)));

        return mb_substr($title ?: 'Peraturan Perusahaan', 0, 255);
    }

    private function companyId(Request $request): int
    {
        /** @var Employee|null $admin */
        $admin = Employee::find(session('admin_id'));
        $companyId = (int) ($admin?->company_id ?: Company::query()->value('id') ?: 1);

        if (! Company::whereKey($companyId)->exists()) {
            $company = new Company(['name' => 'Perusahaan']);
            $company->id = $companyId;
            $company->save();
        }

        return $companyId;
    }

    private function authorizeCompanyRegulation(Request $request, CompanyRegulation $regulation): void
    {
        abort_unless($regulation->company_id === $this->companyId($request), 403);
    }

    private function fillAttachment(CompanyRegulation $regulation, Request $request): void
    {
        $this->fillAttachmentFromFile($regulation, $request->file('attachment'));
    }

    private function fillAttachmentFromFile(CompanyRegulation $regulation, UploadedFile $file): void
    {
        $path = $file->store('company-regulations', 'local');

        $regulation->file_path = $path;
        $regulation->file_name = $file->getClientOriginalName();
        $regulation->file_size = $file->getSize();
        $regulation->file_mime = $file->getClientMimeType();
    }

    private function deleteAttachment(CompanyRegulation $regulation): void
    {
        if ($regulation->file_path) {
            Storage::disk('local')->delete($regulation->file_path);
        }
    }
}

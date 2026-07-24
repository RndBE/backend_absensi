<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\PayrollRunDetail;
use App\Models\PayrollRun;
use App\Models\Company;
use App\Support\AdminPermission;
use App\Support\PayslipBpjsData;
use App\Support\PayslipFilename;
use App\Support\PayslipLoanSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $query = PayrollRunDetail::with([
            'employee:id,full_name,employee_code,department_id,position',
            'employee.department:id,name',
            'payrollRun:id,period,status',
        ])->whereHas('payrollRun', function ($q) {
            $q->whereIn('status', ['published', 'locked']);
        })->when(Schema::hasColumn('payroll_runs', 'company_id'), function ($query) use ($admin) {
            $query->whereHas('payrollRun', fn ($q) => $q->where('company_id', $admin->company_id));
        })->whereHas('employee', function ($q) use ($admin) {
            $q->where('company_id', $admin->company_id);
        });

        if ($request->period) {
            $query->whereHas('payrollRun', function ($q) use ($request) {
                $q->where('period', $request->period);
            });
        }

        $payslips = $query->orderByDesc('id')->get();

        $periods = PayrollRun::whereIn('status', ['published', 'locked'])
            ->when(Schema::hasColumn('payroll_runs', 'company_id'), fn ($q) => $q->where('company_id', $admin->company_id))
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period');

        return view('admin.payslips.index', compact('payslips', 'periods'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'payslip_file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
            'replace_period' => ['nullable', 'boolean'],
        ]);

        $admin = Employee::find(session('admin_id'));
        $period = $request->period;
        $replacePeriod = $request->boolean('replace_period');
        $import = $this->preparePayslipImportRows($this->readImportRows($request->file('payslip_file')));
        $rows = $import['rows'];
        $dataStartLine = $import['data_start_line'];
        $rawHeaders = array_shift($rows) ?? [];
        $headers = array_map(fn ($header) => $this->normalizeImportHeader($header), $rawHeaders);

        if (! in_array('employee_code', $headers, true) && ! in_array('kode_karyawan', $headers, true) && ! in_array('kode', $headers, true)) {
            return back()->with('error', 'Import dibatalkan. Kolom employee_code wajib ada.');
        }

        if (! in_array('basic_salary', $headers, true) && ! in_array('gaji_pokok', $headers, true)) {
            return back()->with('error', 'Import dibatalkan. Kolom basic_salary wajib ada.');
        }

        $componentsByHeader = $this->payrollComponentsByHeader();
        $componentColumns = $this->componentColumns($headers, $rawHeaders);

        $runAttributes = ['period' => $period];
        if (Schema::hasColumn('payroll_runs', 'company_id')) {
            $runAttributes['company_id'] = $admin->company_id;
        }

        $run = PayrollRun::firstOrNew($runAttributes);
        if ($run->exists && $run->status === 'locked') {
            return back()->with('error', 'Import dibatalkan. Payslip periode ini sudah locked.');
        }

        $runValues = [
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $admin?->id,
        ];
        if (Schema::hasColumn('payroll_runs', 'company_id')) {
            $runValues['company_id'] = $admin->company_id;
        }

        $run->fill($runValues);
        $run->save();

        $imported = 0;
        $skipped = 0;
        $deleted = 0;
        $warnings = [];

        if ($replacePeriod) {
            $deleted = PayrollRunDetail::where('payroll_run_id', $run->id)
                ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
                ->delete();
        }

        foreach ($rows as $index => $row) {
            $lineNumber = $index + $dataStartLine;
            $data = $this->combineImportRow($headers, $row);

            if ($this->isBlankImportRow($data)) {
                continue;
            }

            $rowPeriod = trim((string) ($data['period'] ?? $data['periode'] ?? ''));
            if ($rowPeriod !== '' && $rowPeriod !== $period) {
                $skipped++;
                $warnings[] = "Baris {$lineNumber}: periode {$rowPeriod} tidak sesuai.";
                continue;
            }

            $employeeCode = trim((string) ($data['employee_code'] ?? $data['kode_karyawan'] ?? $data['kode'] ?? ''));
            $employee = Employee::where('company_id', $admin->company_id)
                ->where('employee_code', $employeeCode)
                ->first();

            if (! $employee) {
                $skipped++;
                $warnings[] = "Baris {$lineNumber}: kode karyawan {$employeeCode} tidak ditemukan.";
                continue;
            }

            $basicSalary = $this->normalizeImportAmount($data['basic_salary'] ?? $data['gaji_pokok'] ?? 0);
            $components = [];
            $totalEarning = $basicSalary;
            $totalDeduction = 0.0;

            foreach ($componentColumns as $column) {
                $component = $componentsByHeader[$column['component_normalized']] ?? null;
                $componentMeta = $component
                    ? $this->registeredImportComponentMeta($component)
                    : $this->manualImportComponentMeta($column);
                $amount = $this->normalizeImportAmount($data[$column['normalized']] ?? 0);

                $components[] = [
                    'id' => $componentMeta['id'],
                    'name' => $componentMeta['name'],
                    'type' => $componentMeta['type'],
                    'category' => $componentMeta['category'],
                    'amount' => $amount,
                    'is_taxable' => $componentMeta['is_taxable'],
                ];

                if ($componentMeta['type'] === 'earning') {
                    $totalEarning += $amount;
                } elseif ($componentMeta['type'] === 'deduction') {
                    $totalDeduction += $amount;
                }
            }

            PayrollRunDetail::updateOrCreate(
                [
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                ],
                [
                    'basic_salary' => $basicSalary,
                    'total_earning' => $totalEarning,
                    'total_deduction' => $totalDeduction,
                    'net_salary' => $totalEarning - $totalDeduction,
                    'components' => $components,
                    'is_manual_edited' => true,
                ]
            );

            $imported++;
        }

        $run->update([
            'total_earning' => $run->details()->sum('total_earning'),
            'total_deduction' => $run->details()->sum('total_deduction'),
            'total_net' => $run->details()->sum('net_salary'),
        ]);

        $message = "Import payslip selesai: {$imported} berhasil, {$skipped} dilewati.";
        if ($replacePeriod) {
            $message .= " Replace periode menghapus {$deleted} payslip lama.";
        }
        if ($warnings) {
            $message .= ' ' . implode(' ', array_slice($warnings, 0, 5));
        }

        return back()->with($imported > 0 ? 'success' : 'error', $message);
    }

    public function update(Request $request, $id, AdminPermission $permissions)
    {
        $admin = Employee::find(session('admin_id'));
        abort_unless($admin && $permissions->can($admin, 'payroll.runs.update'), 403);

        $detail = PayrollRunDetail::with(['employee', 'payrollRun'])
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        if ($detail->payrollRun?->status === 'locked') {
            return back()->with('error', 'Payslip periode ini sudah locked dan tidak bisa diedit.');
        }

        $request->validate([
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'components' => ['nullable', 'array'],
            'components.*.id' => ['nullable'],
            'components.*.name' => ['required', 'string'],
            'components.*.type' => ['required', 'in:earning,deduction,info'],
            'components.*.category' => ['nullable', 'string'],
            'components.*.amount' => ['required', 'numeric'],
            'components.*.is_taxable' => ['nullable', 'boolean'],
            'components.*.is_auto' => ['nullable', 'boolean'],
            'components.*.detail' => ['nullable', 'string'],
        ]);

        $basicSalary = (float) $request->input('basic_salary', 0);
        $components = $this->normalizePayslipComponents($request->input('components', []), $detail->components ?? []);
        $totalEarning = $basicSalary;
        $totalDeduction = 0.0;

        foreach ($components as $component) {
            if ($component['type'] === 'earning') {
                $totalEarning += (float) $component['amount'];
            } elseif ($component['type'] === 'deduction') {
                $totalDeduction += (float) $component['amount'];
            }
        }

        $detail->update([
            'basic_salary' => $basicSalary,
            'components' => $components,
            'total_earning' => $totalEarning,
            'total_deduction' => $totalDeduction,
            'net_salary' => $totalEarning - $totalDeduction,
            'is_manual_edited' => true,
        ]);

        $this->recalculateRunTotals($detail->payrollRun);

        return back()->with('success', 'Payslip berhasil diperbarui.');
    }

    public function destroy($id, AdminPermission $permissions)
    {
        $admin = Employee::find(session('admin_id'));
        abort_unless($admin && $permissions->can($admin, 'payroll.runs.update'), 403);

        $detail = PayrollRunDetail::with(['employee', 'payrollRun'])
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        $run = $detail->payrollRun;
        if ($run?->status === 'locked') {
            return back()->with('error', 'Payslip periode ini sudah locked dan tidak bisa dihapus.');
        }

        $employeeName = $detail->employee?->full_name ?? 'karyawan';
        $period = $run?->period;

        $detail->delete();
        $this->recalculateRunTotals($run);

        return back()->with('success', "Payslip {$employeeName}".($period ? " periode {$period}" : '').' berhasil dihapus.');
    }

    public function show($id)
    {
        $admin = Employee::find(session('admin_id'));
        $detail = PayrollRunDetail::with([
            'employee',
            'employee.department:id,name',
            'employee.activePayroll',
            'payrollRun:id,period,status',
        ])->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
          ->findOrFail($id);

        $company  = Company::find($detail->employee->company_id);
        $bpjsData = $this->buildBpjsData($detail);
        $loanSummary = PayslipLoanSummary::fromComponents($detail->components);

        return view('admin.payslips.show', compact('detail', 'company', 'bpjsData', 'loanSummary'));
    }

    public function downloadPdf($id)
    {
        $admin = Employee::find(session('admin_id'));
        $detail = PayrollRunDetail::with([
            'employee',
            'employee.department:id,name',
            'employee.activePayroll',
            'payrollRun:id,period,status',
        ])->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
          ->findOrFail($id);

        $company  = Company::find($detail->employee->company_id);
        $bpjsData = $this->buildBpjsData($detail);
        $loanSummary = PayslipLoanSummary::fromComponents($detail->components);

        // Convert logo to base64 for DomPDF inline embedding
        $logoBase64 = null;
        if ($company && $company->logo) {
            $logoPath = storage_path('app/public/' . $company->logo);
            if (file_exists($logoPath)) {
                $logoMime = mime_content_type($logoPath);
                $logoBase64 = 'data:' . $logoMime . ';base64,' . base64_encode(file_get_contents($logoPath));
            }
        }

        $pdf = Pdf::loadView('admin.payslips.pdf', compact('detail', 'company', 'logoBase64', 'bpjsData', 'loanSummary'));
        $pdf->setPaper('A4', 'portrait');

        $filename = PayslipFilename::make($detail->employee->employee_code, $detail->payrollRun->period);

        return $pdf->download($filename);
    }

    /**
     * Unduh SEMUA payslip dalam satu payroll run sebagai SATU file PDF
     * (tiap karyawan satu halaman). Berguna untuk arsip/cetak sekaligus.
     */
    public function downloadRunBundle(Request $request, $runId)
    {
        $admin = Employee::find(session('admin_id'));
        $run = PayrollRun::findOrFail($runId);

        // Urutan payslip: berdasarkan abjad nama (default) atau level lalu tanggal masuk karyawan.
        $sort = $request->query('sort') === 'join_date' ? 'join_date' : 'name';

        $details = PayrollRunDetail::where('payroll_run_id', $run->id)
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->with(['employee', 'employee.department:id,name', 'employee.activePayroll'])
            ->get()
            ->sortBy(function ($d) use ($sort) {
                if ($sort === 'join_date') {
                    // Level/tanggal kosong ditaruh paling akhir.
                    return sprintf(
                        '%06d|%s|%s',
                        $d->employee->job_level ?? 999999,
                        $d->employee->join_date?->format('Y-m-d') ?? '9999-12-31',
                        strtolower($d->employee->full_name ?? '')
                    );
                }
                return strtolower($d->employee->full_name ?? '');
            })
            ->values();

        if ($details->isEmpty()) {
            return back()->with('error', 'Tidak ada payslip untuk diunduh pada payroll run ini.');
        }

        $payslips = $details->map(function (PayrollRunDetail $detail) {
            $company = Company::find($detail->employee->company_id);

            $logoBase64 = null;
            if ($company && $company->logo) {
                $logoPath = storage_path('app/public/'.$company->logo);
                if (file_exists($logoPath)) {
                    $logoBase64 = 'data:'.mime_content_type($logoPath).';base64,'.base64_encode(file_get_contents($logoPath));
                }
            }

            return [
                'detail' => $detail,
                'company' => $company,
                'logoBase64' => $logoBase64,
                'bpjsData' => $this->buildBpjsData($detail),
                'loanSummary' => PayslipLoanSummary::fromComponents($detail->components),
                // Tampilkan benefit (ditanggung perusahaan) — sama seperti download payslip admin per karyawan.
                'hideBenefits' => false,
            ];
        })->all();

        $pdf = Pdf::loadView('admin.payslips.pdf-bulk', ['payslips' => $payslips])
            ->setPaper('A4', 'portrait');

        return $pdf->download('payslips_'.$run->period.'.pdf');
    }

    /**
     * Build structured BPJS benefit data for the payslip view.
     * Only includes programs with non-zero amounts (respects rate settings in DB).
     */
    private function buildBpjsData(PayrollRunDetail $detail): array
    {
        return PayslipBpjsData::fromDetail($detail);
    }

    private function normalizePayslipComponents(mixed $components, mixed $existingComponents = []): array
    {
        if (! is_array($components)) {
            return [];
        }

        $existingComponents = is_array($existingComponents)
            ? array_values($existingComponents)
            : [];

        return collect($components)
            ->map(function (array $component, int $index) use ($existingComponents) {
                $normalized = [
                    'id' => $component['id'] ?? null,
                    'name' => trim((string) ($component['name'] ?? '')),
                    'type' => $component['type'] ?? 'earning',
                    'category' => $component['category'] ?? 'manual',
                    'amount' => round((float) ($component['amount'] ?? 0), 2),
                    'is_taxable' => filter_var($component['is_taxable'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];

                if (array_key_exists('is_auto', $component)) {
                    $normalized['is_auto'] = filter_var($component['is_auto'], FILTER_VALIDATE_BOOLEAN);
                }

                if (($component['detail'] ?? '') !== '') {
                    $normalized['detail'] = trim((string) $component['detail']);
                }

                if (empty($normalized['lines']) && ! empty($existingComponents[$index]['lines'])) {
                    $normalized['lines'] = $existingComponents[$index]['lines'];
                }

                return $normalized;
            })
            ->filter(fn (array $component) => $component['name'] !== '')
            ->values()
            ->all();
    }

    private function recalculateRunTotals(?PayrollRun $run): void
    {
        if (! $run) {
            return;
        }

        $run->update([
            'total_earning' => $run->details()->sum('total_earning'),
            'total_deduction' => $run->details()->sum('total_deduction'),
            'total_net' => $run->details()->sum('net_salary'),
        ]);
    }

    private function readImportRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            return $this->readXlsxRows($file->getRealPath());
        }

        return $this->readCsvRows($file->getRealPath());
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($rows === [] && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function readXlsxRows(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->readXlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName($this->xlsxWorksheetPath($zip, 'PAYSLIP'));
        $zip->close();

        if (! $sheetXml) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $xmlRow) {
            $row = [];
            $maxColumnIndex = -1;
            foreach ($xmlRow->c as $cell) {
                $attributes = $cell->attributes();
                $cellRef = (string) ($attributes['r'] ?? '');
                $columnIndex = $this->xlsxColumnIndex($cellRef);
                $maxColumnIndex = max($maxColumnIndex, $columnIndex);
                $type = (string) ($attributes['t'] ?? '');
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }

                $row[$columnIndex] = $value;
            }

            if ($row !== []) {
                ksort($row);
                $denseRow = [];
                for ($index = 0; $index <= $maxColumnIndex; $index++) {
                    $denseRow[] = $row[$index] ?? '';
                }
                $rows[] = $denseRow;
            }
        }

        return $rows;
    }

    private function xlsxWorksheetPath(ZipArchive $zip, string $preferredSheet): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (! $workbookXml || ! $relsXml) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = simplexml_load_string($workbookXml);
        $relations = simplexml_load_string($relsXml);
        $relationTargets = [];

        foreach ($relations->Relationship ?? [] as $relation) {
            $attributes = $relation->attributes();
            $relationTargets[(string) $attributes['Id']] = (string) $attributes['Target'];
        }

        $fallbackPath = null;
        foreach ($workbook->sheets->sheet ?? [] as $sheet) {
            $attributes = $sheet->attributes();
            $relationAttributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationId = (string) ($relationAttributes['id'] ?? '');
            $target = $relationTargets[$relationId] ?? '';
            $path = $this->normalizeXlsxWorksheetPath($target);

            $fallbackPath ??= $path;
            if (strcasecmp((string) ($attributes['name'] ?? ''), $preferredSheet) === 0) {
                return $path;
            }
        }

        return $fallbackPath ?: 'xl/worksheets/sheet1.xml';
    }

    private function normalizeXlsxWorksheetPath(string $target): string
    {
        if ($target === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        return 'xl/'.ltrim($target, '/');
    }

    private function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! $xml) {
            return [];
        }

        $strings = [];
        $shared = simplexml_load_string($xml);

        foreach ($shared->si ?? [] as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r ?? [] as $run) {
                $text .= (string) ($run->t ?? '');
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function preparePayslipImportRows(array $rows): array
    {
        $firstHeaders = array_map(fn ($header) => $this->normalizeImportHeader($header), $rows[0] ?? []);
        if ($this->hasAnyHeader($firstHeaders, ['employee_code', 'kode_karyawan', 'kode'])) {
            return [
                'rows' => $rows,
                'data_start_line' => 2,
            ];
        }

        $headerRowIndex = $this->salaryReportHeaderRowIndex($rows);
        if ($headerRowIndex === null) {
            return [
                'rows' => $rows,
                'data_start_line' => 2,
            ];
        }

        $mainHeaders = $rows[$headerRowIndex] ?? [];
        $detailHeaders = $rows[$headerRowIndex + 1] ?? [];
        $maxColumns = max(count($mainHeaders), count($detailHeaders));
        $columns = [];
        $groupHeader = '';

        for ($index = 0; $index < $maxColumns; $index++) {
            $mainHeader = trim((string) ($mainHeaders[$index] ?? ''));
            $detailHeader = trim((string) ($detailHeaders[$index] ?? ''));
            $mainNormalized = $this->normalizeImportHeader($mainHeader);
            if ($mainHeader !== '') {
                $groupHeader = $mainHeader;
            }

            if (in_array($mainNormalized, ['employee_id', 'employee_id_'], true)) {
                $columns[] = ['index' => $index, 'header' => 'employee_code'];
                continue;
            }

            if ($mainNormalized === 'basic_salary') {
                $columns[] = ['index' => $index, 'header' => 'basic_salary'];
                continue;
            }

            if ($detailHeader !== '') {
                $columns[] = [
                    'index' => $index,
                    'header' => $detailHeader,
                    'group' => $groupHeader,
                ];
            }
        }

        if ($columns === []) {
            return [
                'rows' => $rows,
                'data_start_line' => 2,
            ];
        }

        $preparedRows = [
            array_map(
                fn ($column) => $column['group'] ?? null
                    ? $column['header'].'__'.$this->normalizeImportHeader($column['group'])
                    : $column['header'],
                $columns
            ),
        ];

        foreach (array_slice($rows, $headerRowIndex + 2) as $row) {
            $firstCell = $this->normalizeImportHeader($row[0] ?? '');
            if (in_array($firstCell, ['grand_total', 'total'], true)) {
                continue;
            }

            $preparedRows[] = array_map(
                fn ($column) => $row[$column['index']] ?? '',
                $columns
            );
        }

        return [
            'rows' => $preparedRows,
            'data_start_line' => $headerRowIndex + 3,
        ];
    }

    private function salaryReportHeaderRowIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $headers = array_map(fn ($header) => $this->normalizeImportHeader($header), $row);
            if ($this->hasAnyHeader($headers, ['employee_id', 'employee_id_']) && in_array('basic_salary', $headers, true)) {
                return $index;
            }
        }

        return null;
    }

    private function hasAnyHeader(array $headers, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $headers, true)) {
                return true;
            }
        }

        return false;
    }

    private function xlsxColumnIndex(string $cellRef): int
    {
        preg_match('/^[A-Z]+/', $cellRef, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function payrollComponentsByHeader()
    {
        $components = PayrollComponent::where('is_active', true)->get();
        $byHeader = $components->keyBy(fn (PayrollComponent $component) => $this->normalizeImportHeader($component->name));
        $aliases = [
            'overtime' => 'Lembur',
            'thr' => 'THR',
            'tunjangan_transportasi' => 'Tunjangan Transport',
            'potongan_terlambat' => 'Potongan Keterlambatan',
            'pinjaman' => 'Potongan Pinjaman',
            'bpjs_k_employee' => 'BPJS Kesehatan',
            'jht_employees' => 'BPJS Ketenagakerjaan',
            'denda_kedisplinan' => 'Denda Ketidakdisiplinan',
        ];

        foreach ($aliases as $alias => $componentName) {
            if ($byHeader->has($alias)) {
                continue;
            }

            $component = $byHeader->get($this->normalizeImportHeader($componentName));
            if ($component) {
                $byHeader->put($alias, $component);
            }
        }

        return $byHeader;
    }

    private function componentColumns(array $headers, array $rawHeaders): array
    {
        $reserved = [
            'employee_code', 'kode_karyawan', 'kode',
            'employee_id',
            'period', 'periode',
            'basic_salary', 'gaji_pokok',
            'name', 'nama', 'full_name', 'nama_karyawan',
            'job_position', 'organization',
            'allowance', 'deduction', 'benefit',
            'total_allowance', 'total_deduction', 'take_home_pay', 'pph_21_payment',
        ];

        return collect($headers)
            ->map(function ($header, $index) use ($rawHeaders) {
                $rawHeader = trim((string) ($rawHeaders[$index] ?? $header));
                [$label, $group] = $this->splitImportHeaderGroup($rawHeader);

                return [
                    'normalized' => $header,
                    'component_normalized' => $this->normalizeImportHeader($label),
                    'label' => $label,
                    'group' => $group,
                ];
            })
            ->filter(fn ($column) => $column['normalized'] !== '' && ! in_array($column['component_normalized'], $reserved, true))
            ->values()
            ->all();
    }

    private function splitImportHeaderGroup(string $header): array
    {
        if (! str_contains($header, '__')) {
            return [$header, ''];
        }

        [$label, $group] = explode('__', $header, 2);

        return [trim($label), trim($group)];
    }

    private function registeredImportComponentMeta(PayrollComponent $component): array
    {
        return [
            'id' => $component->id,
            'name' => $component->name,
            'type' => $component->type,
            'category' => $component->category,
            'is_taxable' => (bool) $component->is_taxable,
        ];
    }

    private function manualImportComponentMeta(array $column): array
    {
        $type = $this->inferManualImportComponentType($column);

        return [
            'id' => null,
            'name' => $column['label'],
            'type' => $type,
            'category' => $type === 'info' ? 'info' : 'manual_import',
            'is_taxable' => $type === 'earning',
        ];
    }

    private function inferManualImportComponentType(array $column): string
    {
        $group = $this->normalizeImportHeader($column['group'] ?? '');
        if (in_array($group, ['benefit'], true)) {
            return 'info';
        }

        if (in_array($group, ['deduction'], true)) {
            return 'deduction';
        }

        if (in_array($group, ['allowance'], true)) {
            return 'earning';
        }

        $name = $column['component_normalized'] ?? '';
        if (str_starts_with($name, 'rate_') || str_contains($name, 'perusahaan')) {
            return 'info';
        }

        foreach (['potongan', 'denda', 'deduction', 'pinjaman', 'pph', 'bpjs', 'jht', 'jp'] as $keyword) {
            if (str_contains($name, $keyword)) {
                return 'deduction';
            }
        }

        return 'earning';
    }

    private function normalizeImportHeader(?string $header): string
    {
        $header = strtolower(trim((string) $header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);

        return trim($header, '_');
    }

    private function combineImportRow(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    private function isBlankImportRow(array $data): bool
    {
        return collect($data)->every(fn ($value) => trim((string) $value) === '');
    }

    private function normalizeImportAmount($value): float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 0.0;
        }

        $value = preg_replace('/[^\d,.\-]/', '', $value);

        if (str_contains($value, ',') && preg_match('/,\d{1,2}$/', $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1 || str_contains($value, ',')) {
            $value = str_replace([',', '.'], '', $value);
        }

        return round((float) $value, 2);
    }
}

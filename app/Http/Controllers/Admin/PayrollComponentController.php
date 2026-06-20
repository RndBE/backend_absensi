<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeePayrollComponent;
use App\Models\LoanRequest;
use App\Models\PayrollComponent;
use App\Support\LoanPayrollComponentSync;
use App\Support\SimpleSpreadsheetReader;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayrollComponentController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type ?? 'all';
        $query = PayrollComponent::withCount('employeeComponents');

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $components = $query->orderBy('type')->orderBy('name')->get();
        return view('admin.payroll-components.index', compact('components', 'type'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:earning,deduction',
            'category'       => 'required|in:fixed,one-time,recurring',
            'default_amount' => 'required|numeric|min:0',
            'is_taxable'     => 'nullable|boolean',
        ]);

        PayrollComponent::create([
            'name'           => $request->name,
            'type'           => $request->type,
            'category'       => $request->category,
            'default_amount' => $request->default_amount,
            'is_taxable'     => $request->boolean('is_taxable'),
        ]);

        return back()->with('success', 'Komponen payroll berhasil dibuat.');
    }

    public function importAssignments(Request $request)
    {
        $request->validate([
            'effective_date' => 'required|date',
            'component_file' => 'required|file|mimes:csv,txt,xlsx|max:5120',
        ]);

        $admin = Employee::find(session('admin_id'));
        $effectiveDate = Carbon::parse($request->effective_date)->toDateString();
        $rows = SimpleSpreadsheetReader::rows(
            $request->file('component_file')->getRealPath(),
            'KOMPONEN MASTER PAYROL FIX',
            $request->file('component_file')->getClientOriginalExtension()
        );
        $prepared = $this->prepareComponentImportRows($rows);

        if ($prepared['columns'] === []) {
            return back()->with('error', 'Import dibatalkan. Sheet KOMPONEN MASTER PAYROL FIX tidak ditemukan atau header tidak sesuai.');
        }

        $componentsByHeader = $this->payrollComponentsByHeader();
        $importedEmployees = 0;
        $assignmentCount = 0;
        $skipped = 0;
        $warnings = [];

        foreach ($prepared['rows'] as $lineOffset => $row) {
            $lineNumber = $prepared['data_start_line'] + $lineOffset;
            $employeeCode = trim((string) ($row[$prepared['employee_code_index']] ?? ''));

            if ($employeeCode === '') {
                $skipped++;
                $warnings[] = "Baris {$lineNumber}: kode karyawan kosong.";
                continue;
            }

            $employee = Employee::where('company_id', $admin->company_id)
                ->where('employee_code', $employeeCode)
                ->first();

            if (! $employee) {
                $skipped++;
                $warnings[] = "Baris {$lineNumber}: karyawan {$employeeCode} tidak ditemukan.";
                continue;
            }

            $basicSalary = $this->normalizeImportAmount($row[$prepared['basic_salary_index']] ?? 0);
            $this->upsertActivePayroll($employee->id, $basicSalary, $effectiveDate);

            foreach ($prepared['columns'] as $column) {
                $component = $componentsByHeader[$column['normalized']] ?? null;
                if (! $component) {
                    $warnings[] = "Kolom {$column['label']} dilewati karena komponen belum terdaftar.";
                    continue;
                }

                $amount = $this->normalizeImportAmount($row[$column['index']] ?? 0);
                EmployeePayrollComponent::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'payroll_component_id' => $component->id,
                    ],
                    [
                        'amount' => $amount,
                        'start_date' => $effectiveDate,
                        'end_date' => null,
                        'is_active' => true,
                    ]
                );

                $this->syncImportedLoanRequest($employee->id, $column['normalized'], $amount, $effectiveDate);

                $assignmentCount++;
            }

            $importedEmployees++;
        }

        $message = "Import komponen payroll selesai: {$importedEmployees} karyawan, {$assignmentCount} assignment dibuat/diperbarui, {$skipped} dilewati.";
        if ($warnings) {
            $message .= ' ' . implode(' ', array_slice(array_unique($warnings), 0, 5));
        }

        return back()->with($importedEmployees > 0 ? 'success' : 'error', $message);
    }

    public function update(Request $request, $id)
    {
        $component = PayrollComponent::findOrFail($id);

        $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:earning,deduction',
            'category'       => 'required|in:fixed,one-time,recurring',
            'default_amount' => 'required|numeric|min:0',
            'is_taxable'     => 'nullable|boolean',
        ]);

        $component->update([
            'name'           => $request->name,
            'type'           => $request->type,
            'category'       => $request->category,
            'default_amount' => $request->default_amount,
            'is_taxable'     => $request->boolean('is_taxable'),
        ]);

        return back()->with('success', 'Komponen payroll berhasil diperbarui.');
    }

    public function toggle($id)
    {
        $component = PayrollComponent::findOrFail($id);
        $component->update(['is_active' => !$component->is_active]);
        return back()->with('success', 'Status komponen berhasil diubah.');
    }

    public function destroy($id)
    {
        $component = PayrollComponent::findOrFail($id);

        if ($component->employeeComponents()->exists()) {
            return back()->with('error', 'Tidak bisa hapus komponen yang sedang di-assign ke karyawan.');
        }

        $component->delete();
        return back()->with('success', 'Komponen payroll berhasil dihapus.');
    }

    // ─── Employee Assignment Management ───────────────────────────────────

    public function employees(Request $request, $id)
    {
        $component = PayrollComponent::findOrFail($id);

        // Already-assigned employee IDs
        $assignedIds = EmployeePayrollComponent::where('payroll_component_id', $id)
            ->pluck('employee_id')
            ->toArray();

        // Assigned list with search
        $assignedQuery = EmployeePayrollComponent::with('employee.department')
            ->where('payroll_component_id', $id);

        if ($request->search) {
            $assignedQuery->whereHas('employee', function ($q) use ($request) {
                $q->where('full_name', 'like', "%{$request->search}%")
                  ->orWhere('employee_code', 'like', "%{$request->search}%");
            });
        }

        $assignments = $assignedQuery->orderByDesc('is_active')->orderBy('id')->get();

        // Employees NOT yet assigned (for the add form)
        $unassigned = Employee::whereNotIn('id', $assignedIds)
            ->where('is_active', true)
            ->with('department:id,name')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code', 'department_id']);

        return view('admin.payroll-components.employees', compact(
            'component', 'assignments', 'unassigned'
        ));
    }

    public function assignEmployee(Request $request, $id)
    {
        $component = PayrollComponent::findOrFail($id);

        $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'amount'         => 'nullable|numeric|min:0',
            'start_date'     => 'nullable|date',
        ]);

        $amount = $request->amount ?? $component->default_amount;
        $added  = 0;
        $skipped = 0;

        foreach ($request->employee_ids as $empId) {
            $exists = EmployeePayrollComponent::where('payroll_component_id', $id)
                ->where('employee_id', $empId)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            EmployeePayrollComponent::create([
                'payroll_component_id' => $id,
                'employee_id'          => $empId,
                'amount'               => $amount,
                'start_date'           => $request->start_date,
                'is_active'            => true,
            ]);
            $added++;
        }

        $msg = "Berhasil assign ke {$added} karyawan.";
        if ($skipped) $msg .= " {$skipped} karyawan dilewati (sudah ada).";

        return back()->with('success', $msg);
    }

    public function updateAssignment(Request $request, $id, $assignId)
    {
        $assignment = EmployeePayrollComponent::where('payroll_component_id', $id)
            ->findOrFail($assignId);

        $request->validate([
            'amount'     => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
            'is_active'  => 'nullable|boolean',
        ]);

        $assignment->update([
            'amount'     => $request->amount,
            'start_date' => $request->filled('start_date') ? $request->start_date : $assignment->start_date,
            'end_date'   => $request->filled('end_date')   ? $request->end_date   : $assignment->end_date,
            'is_active'  => $request->boolean('is_active', $assignment->is_active),
        ]);

        return back()->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function removeAssignment($id, $assignId)
    {
        $assignment = EmployeePayrollComponent::where('payroll_component_id', $id)
            ->findOrFail($assignId);

        $assignment->delete();
        return back()->with('success', 'Karyawan berhasil dihapus dari komponen ini.');
    }

    private function prepareComponentImportRows(array $rows): array
    {
        $headerRowIndex = null;

        foreach ($rows as $index => $row) {
            $headers = array_map(fn ($header) => $this->normalizeImportHeader($header), $row);
            if ($this->hasAnyHeader($headers, ['employee_id', 'employee_id_', 'employee_code', 'kode_karyawan']) && in_array('basic_salary', $headers, true)) {
                $headerRowIndex = $index;
                break;
            }
        }

        if ($headerRowIndex === null) {
            return ['columns' => [], 'rows' => [], 'data_start_line' => 0];
        }

        $mainHeaders = $rows[$headerRowIndex] ?? [];
        $detailHeaders = $rows[$headerRowIndex + 1] ?? [];
        $employeeCodeIndex = null;
        $basicSalaryIndex = null;
        $columns = [];
        $groupHeader = '';
        $maxColumns = max(count($mainHeaders), count($detailHeaders));

        for ($index = 0; $index < $maxColumns; $index++) {
            $mainHeader = trim((string) ($mainHeaders[$index] ?? ''));
            $detailHeader = trim((string) ($detailHeaders[$index] ?? ''));
            $mainNormalized = $this->normalizeImportHeader($mainHeader);

            if ($mainHeader !== '') {
                $groupHeader = $mainHeader;
            }

            if (in_array($mainNormalized, ['employee_id', 'employee_id_', 'employee_code', 'kode_karyawan'], true)) {
                $employeeCodeIndex = $index;
                continue;
            }

            if ($mainNormalized === 'basic_salary') {
                $basicSalaryIndex = $index;
                continue;
            }

            if ($detailHeader !== '' && in_array($this->normalizeImportHeader($groupHeader), ['allowance', 'deduction'], true)) {
                $columns[] = [
                    'index' => $index,
                    'label' => $detailHeader,
                    'normalized' => $this->normalizeImportHeader($detailHeader),
                ];
            }
        }

        if ($employeeCodeIndex === null || $basicSalaryIndex === null) {
            return ['columns' => [], 'rows' => [], 'data_start_line' => 0];
        }

        $dataRows = [];
        foreach (array_slice($rows, $headerRowIndex + 2) as $row) {
            $firstCell = $this->normalizeImportHeader($row[$employeeCodeIndex] ?? '');
            if ($firstCell === '' || in_array($firstCell, ['grand_total', 'total'], true)) {
                continue;
            }

            $dataRows[] = $row;
        }

        return [
            'employee_code_index' => $employeeCodeIndex,
            'basic_salary_index' => $basicSalaryIndex,
            'columns' => $columns,
            'rows' => $dataRows,
            'data_start_line' => $headerRowIndex + 3,
        ];
    }

    private function upsertActivePayroll(int $employeeId, float $basicSalary, string $effectiveDate): void
    {
        EmployeePayroll::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'is_active' => true,
            ],
            [
                'basic_salary' => $basicSalary,
                'payment_schedule' => 'monthly',
                'payment_method' => 'transfer',
                'effective_date' => $effectiveDate,
                'is_active' => true,
            ]
        );
    }

    private function syncImportedLoanRequest(int $employeeId, string $column, float $amount, string $effectiveDate): void
    {
        if ($column !== 'pinjaman' || $amount <= 0) {
            return;
        }

        $startPeriod = Carbon::parse($effectiveDate)->format('Y-m');

        LoanRequest::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'start_period' => $startPeriod,
                'purpose' => 'Import komponen Pinjaman',
            ],
            [
                'amount' => $amount,
                'interest_rate' => 0,
                'interest_amount' => 0,
                'total_repayable' => $amount,
                'installment_count' => 1,
                'monthly_installment' => $amount,
                'remaining_amount' => $amount,
                'status' => 'active',
                'disbursed_at' => Carbon::parse($effectiveDate)->startOfDay(),
                'paid_at' => null,
            ]
        );

        LoanPayrollComponentSync::syncEmployee($employeeId);
    }

    private function payrollComponentsByHeader(): array
    {
        $components = PayrollComponent::where('is_active', true)->get();
        $byHeader = $components->keyBy(fn (PayrollComponent $component) => $this->normalizeImportHeader($component->name));
        $aliases = [
            'bpjs_k_employee' => 'BPJS Kesehatan',
            'jht_employees' => 'BPJS Ketenagakerjaan',
            'tunjangan_transportasi' => 'Tunjangan Transportasi',
        ];

        foreach ($aliases as $alias => $componentName) {
            $component = $byHeader->get($this->normalizeImportHeader($componentName));
            if ($component) {
                $byHeader->put($alias, $component);
            }
        }

        return $byHeader->all();
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

    private function normalizeImportHeader(?string $header): string
    {
        $header = strtolower(trim((string) $header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);

        return trim($header, '_');
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $query = PayrollAdjustment::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->with(['employee', 'creator']);

        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->target_period) {
            $query->where('target_period', $request->target_period);
        }

        $adjustments = $query->orderByDesc('created_at')->paginate(20);
        return view('admin.payroll-adjustments.index', compact('adjustments'));
    }

    public function create()
    {
        $admin = Employee::find(session('admin_id'));
        $employees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->orderBy('full_name')->get();
        return view('admin.payroll-adjustments.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:adjustment,correction,backpay,arrears,retroactive',
            'earning_type' => 'required|in:earning,deduction',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'reference_period' => 'nullable|string|size:7',
            'target_period' => 'required|string|size:7',
            'notes' => 'nullable|string',
        ]);

        $admin = Employee::find(session('admin_id'));
        Employee::where('company_id', $admin->company_id)->findOrFail($request->employee_id);

        PayrollAdjustment::create([
            ...$request->only(['employee_id', 'type', 'earning_type', 'name', 'amount', 'reference_period', 'target_period', 'notes']),
            'created_by' => $admin->id,
        ]);

        return redirect()->route('admin.payroll-adjustments.index')->with('success', 'Adjustment berhasil dibuat.');
    }

    public function bulkCreate()
    {
        $admin = Employee::find(session('admin_id'));
        $employees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->orderBy('full_name')->get();
        return view('admin.payroll-adjustments.bulk', compact('employees'));
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            'type' => 'required|in:adjustment,correction,backpay,arrears,retroactive',
            'target_period' => 'required|string|size:7',
        ]);

        $admin = Employee::find(session('admin_id'));
        $file = $request->file('csv_file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_shift($rows);

        // Expected CSV: employee_code, name, earning_type, amount, notes
        $headerMap = array_flip(array_map('strtolower', array_map('trim', $header)));

        $codeIdx = $headerMap['employee_code'] ?? $headerMap['kode_karyawan'] ?? $headerMap['code'] ?? 0;
        $nameIdx = $headerMap['name'] ?? $headerMap['nama'] ?? $headerMap['keterangan'] ?? 1;
        $typeIdx = $headerMap['earning_type'] ?? $headerMap['tipe'] ?? $headerMap['type'] ?? 2;
        $amountIdx = $headerMap['amount'] ?? $headerMap['nominal'] ?? $headerMap['jumlah'] ?? 3;
        $notesIdx = $headerMap['notes'] ?? $headerMap['catatan'] ?? 4;

        $created = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                if (count($row) < 4) continue;

                $empCode = trim($row[$codeIdx] ?? '');
                $emp = Employee::where('employee_code', $empCode)->where('company_id', $admin->company_id)->first();

                if (!$emp) {
                    $errors[] = "Baris " . ($i + 2) . ": Karyawan '{$empCode}' tidak ditemukan";
                    continue;
                }

                $earningType = strtolower(trim($row[$typeIdx] ?? 'deduction'));
                if (!in_array($earningType, ['earning', 'deduction'])) $earningType = 'deduction';

                PayrollAdjustment::create([
                    'employee_id' => $emp->id,
                    'type' => $request->type,
                    'earning_type' => $earningType,
                    'name' => trim($row[$nameIdx] ?? 'Adjustment'),
                    'amount' => abs((float) str_replace(['.', ','], ['', '.'], $row[$amountIdx] ?? 0)),
                    'target_period' => $request->target_period,
                    'notes' => trim($row[$notesIdx] ?? ''),
                    'created_by' => $admin->id,
                ]);
                $created++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal import: ' . $e->getMessage());
        }

        $msg = "{$created} adjustment berhasil di-import.";
        if (count($errors) > 0) {
            $msg .= ' ' . count($errors) . ' error: ' . implode('; ', array_slice($errors, 0, 5));
        }

        return redirect()->route('admin.payroll-adjustments.index')->with('success', $msg);
    }

    public function cancel($id)
    {
        $admin = Employee::find(session('admin_id'));
        $adj = PayrollAdjustment::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);
        if ($adj->status !== 'pending') {
            return back()->with('error', 'Hanya adjustment pending yang bisa dibatalkan.');
        }
        $adj->update(['status' => 'cancelled']);
        return back()->with('success', 'Adjustment dibatalkan.');
    }

    public function generateBackpay(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'reference_period' => 'required|string|size:7',
            'target_period' => 'required|string|size:7',
        ]);

        $admin = Employee::find(session('admin_id'));
        Employee::where('company_id', $admin->company_id)->findOrFail($request->employee_id);
        $empId = $request->employee_id;

        // Get the employee's current and previous salary
        $currentPayroll = \App\Models\EmployeePayroll::where('employee_id', $empId)
            ->where('is_active', true)->first();

        if (!$currentPayroll) {
            return back()->with('error', 'Data payroll aktif tidak ditemukan.');
        }

        // Get the payroll run detail for the reference period
        $refDetail = \App\Models\PayrollRunDetail::whereHas('payrollRun', function ($q) use ($request) {
            $q->where('period', $request->reference_period);
        })->where('employee_id', $empId)->first();

        if (!$refDetail) {
            return back()->with('error', 'Data payroll periode referensi tidak ditemukan.');
        }

        // Calculate difference
        $oldBasic = (float) $refDetail->basic_salary;
        $newBasic = (float) $currentPayroll->basic_salary;
        $diff = $newBasic - $oldBasic;

        if ($diff <= 0) {
            return back()->with('error', 'Tidak ada selisih gaji untuk backpay (gaji baru harus lebih besar).');
        }

        PayrollAdjustment::create([
            'employee_id' => $empId,
            'type' => 'backpay',
            'earning_type' => 'earning',
            'name' => 'Backpay selisih gaji ' . $request->reference_period,
            'amount' => $diff,
            'reference_period' => $request->reference_period,
            'target_period' => $request->target_period,
            'notes' => "Selisih: Rp " . number_format($newBasic, 0, ',', '.') . " - Rp " . number_format($oldBasic, 0, ',', '.') . " = Rp " . number_format($diff, 0, ',', '.'),
            'created_by' => $admin->id,
        ]);

        return redirect()->route('admin.payroll-adjustments.index')->with('success', "Backpay Rp " . number_format($diff, 0, ',', '.') . " berhasil dibuat.");
    }
}

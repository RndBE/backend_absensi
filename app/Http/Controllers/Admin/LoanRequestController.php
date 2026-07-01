<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LoanRequest;
use App\Support\LoanPayrollComponentSync;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanRequestController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $query = LoanRequest::with(['employee:id,full_name,employee_code,department_id,company_id', 'employee.department:id,name'])
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id));

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', fn ($q) => $q
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%"));
        }

        $loanRequests = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $summary = [
            'active' => (clone $query)->where('status', 'active')->count(),
            'paid' => (clone $query)->where('status', 'paid')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
        ];

        return view('admin.loan-requests.index', compact('loanRequests', 'summary'));
    }

    public function create()
    {
        $employees = $this->employeeOptions();

        return view('admin.loan-requests.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $data = $this->prepareLoanData($this->validatedData($request));

        $loan = LoanRequest::create($data);
        LoanPayrollComponentSync::syncEmployee($loan->employee_id);

        return redirect()->route('admin.loan-requests.index')
            ->with('success', 'Data pinjaman berhasil ditambahkan.');
    }

    public function show(int $id)
    {
        $loanRequest = $this->findLoanForCurrentCompany($id);

        return view('admin.loan-requests.show', compact('loanRequest'));
    }

    public function edit(int $id)
    {
        $loanRequest = $this->findLoanForCurrentCompany($id);
        $employees = $this->employeeOptions();

        return view('admin.loan-requests.edit', compact('loanRequest', 'employees'));
    }

    public function update(Request $request, int $id)
    {
        $loanRequest = $this->findLoanForCurrentCompany($id);
        $data = $this->prepareLoanData($this->validatedData($request));

        $loanRequest->update($data);
        LoanPayrollComponentSync::syncEmployee($loanRequest->employee_id);

        return redirect()->route('admin.loan-requests.show', $loanRequest->id)
            ->with('success', 'Data pinjaman berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $loanRequest = $this->findLoanForCurrentCompany($id);
        $employeeId = $loanRequest->employee_id;
        $loanRequest->delete();
        LoanPayrollComponentSync::syncEmployee($employeeId);

        return redirect()->route('admin.loan-requests.index')
            ->with('success', 'Data pinjaman berhasil dihapus.');
    }

    private function validatedData(Request $request): array
    {
        $admin = Employee::find(session('admin_id'));

        return $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('company_id', $admin->company_id),
            ],
            'amount' => ['required', 'numeric', 'min:1'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:1080'],
            'monthly_installment' => ['nullable', 'numeric', 'min:0'],
            'remaining_amount' => ['nullable', 'numeric', 'min:0'],
            'start_period' => ['nullable', 'date_format:Y-m'],
            'status' => ['required', Rule::in(['active', 'paid', 'cancelled'])],
            'purpose' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function prepareLoanData(array $data): array
    {
        $amount = (float) $data['amount'];
        $interestRate = (float) ($data['interest_rate'] ?? 0);
        $interestAmount = round($amount * ($interestRate / 100), 2);
        $totalRepayable = round($amount + $interestAmount, 2);

        $data['interest_rate'] = $interestRate;
        $data['interest_amount'] = $interestAmount;
        $data['total_repayable'] = $totalRepayable;
        $data['monthly_installment'] = $this->monthlyInstallment($data, $totalRepayable);
        $data['remaining_amount'] = $this->hasInputAmount($data, 'remaining_amount')
            ? (float) $data['remaining_amount']
            : $totalRepayable;

        return $data;
    }

    private function monthlyInstallment(array $data, float $totalRepayable): float
    {
        if ($this->hasInputAmount($data, 'monthly_installment')) {
            return (float) $data['monthly_installment'];
        }

        return round($totalRepayable / max((int) $data['installment_count'], 1), 2);
    }

    private function hasInputAmount(array $data, string $key): bool
    {
        return array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '';
    }

    private function employeeOptions()
    {
        $admin = Employee::find(session('admin_id'));

        return Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code']);
    }

    private function findLoanForCurrentCompany(int $id): LoanRequest
    {
        $admin = Employee::find(session('admin_id'));

        $loanRequest = LoanRequest::with([
            'employee:id,full_name,employee_code,department_id,company_id,position',
            'employee.department:id,name',
        ])->findOrFail($id);

        abort_if($loanRequest->employee?->company_id !== $admin->company_id, 403);

        return $loanRequest;
    }
}

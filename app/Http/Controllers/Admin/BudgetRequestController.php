<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use Illuminate\Http\Request;

class BudgetRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = BudgetRequest::with([
            'employee:id,full_name,photo,department_id',
            'employee.department:id,name',
            'items',
        ]);

        // Manager: hanya melihat departemennya sendiri.
        if ($dept = \App\Support\AdminDataScope::departmentId(\App\Models\Employee::find(session('admin_id')))) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $dept));
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by month
        if ($request->filled('month')) {
            $date = \Carbon\Carbon::parse($request->month . '-01');
            $query->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->where('full_name', 'like', "%{$search}%");
                    });
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.budget-requests.index', compact('requests'));
    }

    public function show($id)
    {
        $admin = Employee::find(session('admin_id'));
        $budgetRequest = BudgetRequest::with([
            'employee:id,full_name,photo,department_id,position,job_level,company_id',
            'employee.department:id,name',
            'items.attachments',
            'attachments',
            'participants:id,full_name,photo',
            'approvalLogs.approver:id,full_name,photo',
            'payments.processor:id,full_name',
        ])->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
          ->findOrFail($id);

        return view('admin.budget-requests.show', compact('budgetRequest'));
    }

    /**
     * Override batas pengumpulan LHP (hari kerja) untuk pengajuan ini — keringanan HR.
     * Kosongkan untuk kembali ke default global.
     */
    public function updateLhpDeadline(Request $request, $id)
    {
        $admin = Employee::find(session('admin_id'));

        if (! app(\App\Support\AdminPermission::class)->can($admin, 'budget.manage')) {
            return back()->with('error', 'Anda tidak berhak mengubah batas LHP.');
        }

        $validated = $request->validate([
            'lhp_deadline_days' => 'nullable|integer|min:1|max:60',
        ], [
            'lhp_deadline_days.min' => 'Batas minimal 1 hari kerja.',
            'lhp_deadline_days.max' => 'Batas maksimal 60 hari kerja.',
        ]);

        $budgetRequest = BudgetRequest::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        $budgetRequest->update(['lhp_deadline_days' => $validated['lhp_deadline_days'] ?? null]);

        return back()->with('success', 'Batas pengumpulan LHP diperbarui.');
    }

    public function print($id)
    {
        $admin = Employee::find(session('admin_id'));
        $budgetRequest = BudgetRequest::with([
            'employee:id,full_name,photo,signature,department_id,position,job_level',
            'employee.department:id,name',
            'items.attachments',
            'attachments',
            'participants:id,full_name,photo',
            'approvalLogs.approver:id,full_name,signature,position,job_level',
            'travelZone',
        ])->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
          ->findOrFail($id);

        return view('budget-requests.print', [
            'budgetRequest' => $budgetRequest,
            'approvalChain' => EmployeeApprover::getChain($budgetRequest->employee_id, 'budget'),
            'backUrl' => route('admin.budget-requests.show', $budgetRequest->id),
        ]);
    }

    public function destroy($id)
    {
        $admin = Employee::find(session('admin_id'));
        if ($admin->role !== 'superadmin') {
            return back()->with('error', 'Hanya superadmin yang dapat menghapus pengajuan.');
        }

        $budgetRequest = BudgetRequest::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        // Delete related
        $budgetRequest->items()->each(fn($item) => $item->attachments()->delete());
        $budgetRequest->items()->delete();
        $budgetRequest->attachments()->delete();
        $budgetRequest->approvalLogs()->delete();
        $budgetRequest->participants()->detach();
        $budgetRequest->delete();

        return redirect()->route('admin.budget-requests.index')
            ->with('success', 'Pengajuan anggaran berhasil dihapus.');
    }
}

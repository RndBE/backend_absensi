<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRule;
use App\Models\Employee;
use Illuminate\Http\Request;

class ApprovalRuleController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $activeType = $request->type ?? 'leave';

        $types = [
            'leave' => 'Cuti',
            'overtime' => 'Lembur',
            'attendance' => 'Presensi',
            'data-change' => 'Perubahan Data',
        ];

        $rules = ApprovalRule::where('company_id', $admin->company_id)
            ->where('request_type', $activeType)
            ->orderBy('step_order')
            ->get();

        return view('admin.approval-rules.index', compact('rules', 'types', 'activeType'));
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $request->validate([
            'request_type' => 'required|in:leave,overtime,attendance,data-change',
            'name' => 'required|string|max:255',
            'requester_min_level' => 'nullable|integer|min:1',
            'requester_max_level' => 'nullable|integer|min:1',
            'min_approver_level' => 'nullable|integer|min:1',
            'approver_role' => 'required|in:admin,manager,any',
        ]);

        // Auto-calculate next step_order within same requester level group
        $maxStep = ApprovalRule::where('company_id', $admin->company_id)
            ->where('request_type', $request->request_type)
            ->where('requester_min_level', $request->requester_min_level)
            ->where('requester_max_level', $request->requester_max_level)
            ->max('step_order') ?? 0;

        ApprovalRule::create([
            'company_id' => $admin->company_id,
            'request_type' => $request->request_type,
            'requester_min_level' => $request->requester_min_level,
            'requester_max_level' => $request->requester_max_level,
            'step_order' => $maxStep + 1,
            'name' => $request->name,
            'min_approver_level' => $request->min_approver_level,
            'approver_role' => $request->approver_role,
        ]);

        return redirect()->route('admin.approval-rules.index', ['type' => $request->request_type])
            ->with('success', 'Step approval berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $rule = ApprovalRule::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'min_approver_level' => 'nullable|integer|min:1',
            'approver_role' => 'required|in:admin,manager,any',
        ]);

        $rule->update($request->only('name', 'min_approver_level', 'approver_role'));

        return redirect()->route('admin.approval-rules.index', ['type' => $rule->request_type])
            ->with('success', 'Step approval berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $rule = ApprovalRule::findOrFail($id);
        $type = $rule->request_type;
        $companyId = $rule->company_id;

        $rule->delete();

        // Re-order remaining steps
        $remaining = ApprovalRule::where('company_id', $companyId)
            ->where('request_type', $type)
            ->orderBy('step_order')
            ->get();

        foreach ($remaining as $i => $r) {
            $r->update(['step_order' => $i + 1]);
        }

        return redirect()->route('admin.approval-rules.index', ['type' => $type])
            ->with('success', 'Step approval berhasil dihapus.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:approval_rules,id',
        ]);

        foreach ($request->ids as $index => $id) {
            ApprovalRule::where('id', $id)->update(['step_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function toggle($id)
    {
        $rule = ApprovalRule::findOrFail($id);
        $rule->update(['is_active' => !$rule->is_active]);

        return redirect()->route('admin.approval-rules.index', ['type' => $rule->request_type])
            ->with('success', 'Status step berhasil diubah.');
    }
}

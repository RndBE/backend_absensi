<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollGroup;
use Illuminate\Http\Request;

class PayrollGroupController extends Controller
{
    public function index()
    {
        $groups = PayrollGroup::withCount('employeePayrolls')->orderBy('name')->get();
        return view('admin.payroll-groups.index', compact('groups'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:payroll_groups,name',
            'description' => 'nullable|string',
        ]);

        PayrollGroup::create($request->only('name', 'description'));
        return back()->with('success', 'Payroll group berhasil dibuat.');
    }

    public function update(Request $request, $id)
    {
        $group = PayrollGroup::findOrFail($id);

        $request->validate([
            'name' => "required|string|max:255|unique:payroll_groups,name,{$id}",
            'description' => 'nullable|string',
        ]);

        $group->update($request->only('name', 'description'));
        return back()->with('success', 'Payroll group berhasil diperbarui.');
    }

    public function toggle($id)
    {
        $group = PayrollGroup::findOrFail($id);
        $group->update(['is_active' => !$group->is_active]);
        return back()->with('success', 'Status group berhasil diubah.');
    }

    public function destroy($id)
    {
        $group = PayrollGroup::findOrFail($id);

        if ($group->employeePayrolls()->exists()) {
            return back()->with('error', 'Tidak bisa hapus group yang masih digunakan.');
        }

        $group->delete();
        return back()->with('success', 'Payroll group berhasil dihapus.');
    }
}

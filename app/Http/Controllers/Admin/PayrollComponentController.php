<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollComponent;
use Illuminate\Http\Request;

class PayrollComponentController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type ?? 'all';
        $query = PayrollComponent::query();

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $components = $query->orderBy('type')->orderBy('name')->get();
        return view('admin.payroll-components.index', compact('components', 'type'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:earning,deduction',
            'category' => 'required|in:fixed,one-time,recurring',
            'default_amount' => 'required|numeric|min:0',
            'is_taxable' => 'nullable|boolean',
        ]);

        PayrollComponent::create([
            'name' => $request->name,
            'type' => $request->type,
            'category' => $request->category,
            'default_amount' => $request->default_amount,
            'is_taxable' => $request->boolean('is_taxable'),
        ]);

        return back()->with('success', 'Komponen payroll berhasil dibuat.');
    }

    public function update(Request $request, $id)
    {
        $component = PayrollComponent::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:earning,deduction',
            'category' => 'required|in:fixed,one-time,recurring',
            'default_amount' => 'required|numeric|min:0',
            'is_taxable' => 'nullable|boolean',
        ]);

        $component->update([
            'name' => $request->name,
            'type' => $request->type,
            'category' => $request->category,
            'default_amount' => $request->default_amount,
            'is_taxable' => $request->boolean('is_taxable'),
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
}

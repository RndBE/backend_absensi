<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $admin = Employee::find(session('admin_id'));

        // Get top-level departments with children and employee counts
        $departments = Department::where('company_id', $admin->company_id)
            ->whereNull('parent_id')
            ->with(['children' => function ($q) {
                $q->withCount('employees');
            }])
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        // Also get all departments flat for the parent dropdown
        $allDepartments = Department::where('company_id', $admin->company_id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('admin.departments.index', compact('departments', 'allDepartments'));
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:departments,id',
        ]);

        Department::create([
            'company_id' => $admin->company_id,
            'parent_id' => $request->parent_id,
            'name' => strtoupper($request->name),
        ]);

        return back()->with('success', 'Departemen berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $dept = Department::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:departments,id',
        ]);

        // Prevent setting parent to self or own children
        if ($request->parent_id == $id) {
            return back()->with('error', 'Departemen tidak bisa menjadi sub dari dirinya sendiri.');
        }

        $dept->update([
            'name' => strtoupper($request->name),
            'parent_id' => $request->parent_id,
        ]);

        return back()->with('success', 'Departemen berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $dept = Department::withCount(['employees', 'children'])->findOrFail($id);

        if ($dept->employees_count > 0) {
            return back()->with('error', "Tidak bisa hapus — masih ada {$dept->employees_count} karyawan di departemen ini.");
        }

        if ($dept->children_count > 0) {
            return back()->with('error', "Tidak bisa hapus — masih ada {$dept->children_count} sub-divisi di departemen ini.");
        }

        $dept->delete();
        return back()->with('success', 'Departemen berhasil dihapus.');
    }
}

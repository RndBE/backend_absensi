<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function index()
    {
        $leaveTypes = LeaveType::withCount(['leaveRequests', 'leaveBalances'])->orderBy('name')->get();
        return view('admin.leave-types.index', compact('leaveTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:leave_types,name',
        ]);

        LeaveType::create([
            'name'     => $request->name,
            'max_days' => 12,
        ]);

        return back()->with('success', 'Tipe cuti berhasil ditambahkan.');
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:leave_types,name,' . $leaveType->id,
        ]);

        $leaveType->update([
            'name' => $request->name,
        ]);

        return back()->with('success', 'Tipe cuti berhasil diperbarui.');
    }

    public function destroy(LeaveType $leaveType)
    {
        if ($leaveType->leaveRequests()->exists()) {
            return back()->with('error', 'Tidak bisa hapus — tipe cuti ini sudah digunakan dalam pengajuan cuti.');
        }

        $leaveType->delete();
        return back()->with('success', 'Tipe cuti berhasil dihapus.');
    }
}

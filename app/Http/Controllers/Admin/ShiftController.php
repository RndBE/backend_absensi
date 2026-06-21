<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index()
    {
        $admin = Employee::find(session('admin_id'));
        $shifts = Shift::where('company_id', $admin->company_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.shifts.index', compact('shifts'));
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $request->validate([
            'name'          => 'required|string|max:255',
            'start_time'    => 'nullable|date_format:H:i',
            'end_time'      => 'nullable|date_format:H:i',
            'color'         => 'required|string|max:7',
            'is_off'        => 'sometimes|boolean',
            'is_overnight'  => 'sometimes|boolean',
            'work_hours'    => 'nullable|integer|min:1|max:24',
            'auto_overtime' => 'sometimes|boolean',
        ]);

        $isOff = $request->boolean('is_off');

        Shift::create([
            'company_id'    => $admin->company_id,
            'name'          => $request->name,
            'start_time'    => $isOff ? null : $request->start_time,
            'end_time'      => $isOff ? null : $request->end_time,
            'color'         => $request->color,
            'is_off'        => $isOff,
            'is_overnight'  => !$isOff && $request->boolean('is_overnight'),
            'sort_order'    => Shift::where('company_id', $admin->company_id)->max('sort_order') + 1,
            'work_hours'    => (!$isOff && $request->boolean('auto_overtime')) ? $request->work_hours : null,
            'auto_overtime' => !$isOff && $request->boolean('auto_overtime'),
        ]);

        return back()->with('success', 'Shift berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);

        $request->validate([
            'name'          => 'required|string|max:255',
            'start_time'    => 'nullable|date_format:H:i',
            'end_time'      => 'nullable|date_format:H:i',
            'color'         => 'required|string|max:7',
            'is_off'        => 'sometimes|boolean',
            'is_overnight'  => 'sometimes|boolean',
            'work_hours'    => 'nullable|integer|min:1|max:24',
            'auto_overtime' => 'sometimes|boolean',
        ]);

        $isOff = $request->boolean('is_off');

        $shift->update([
            'name'          => $request->name,
            'start_time'    => $isOff ? null : $request->start_time,
            'end_time'      => $isOff ? null : $request->end_time,
            'color'         => $request->color,
            'is_off'        => $isOff,
            'is_overnight'  => !$isOff && $request->boolean('is_overnight'),
            'work_hours'    => (!$isOff && $request->boolean('auto_overtime')) ? $request->work_hours : null,
            'auto_overtime' => !$isOff && $request->boolean('auto_overtime'),
        ]);

        return back()->with('success', 'Shift berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $shift = Shift::withCount('assignments')->findOrFail($id);

        if ($shift->assignments_count > 0) {
            return back()->with('error', "Tidak bisa hapus — masih ada {$shift->assignments_count} jadwal menggunakan shift ini.");
        }

        $shift->delete();
        return back()->with('success', 'Shift berhasil dihapus.');
    }
}

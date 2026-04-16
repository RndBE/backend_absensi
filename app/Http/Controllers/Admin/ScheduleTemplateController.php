<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateDay;
use App\Models\Shift;
use Illuminate\Http\Request;

class ScheduleTemplateController extends Controller
{
    public function index()
    {
        $admin = Employee::find(session('admin_id'));
        $templates = ScheduleTemplate::where('company_id', $admin->company_id)
            ->with(['days.shift', 'employees:id,schedule_template_id'])
            ->withCount('employees')
            ->get();

        $shifts = Shift::where('company_id', $admin->company_id)->orderBy('sort_order')->get();

        return view('admin.schedule-templates.index', compact('templates', 'shifts'));
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'days' => 'required|array|size:7',
            'days.*' => 'required|exists:shifts,id',
        ]);

        $template = ScheduleTemplate::create([
            'company_id' => $admin->company_id,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        foreach ($request->days as $dayOfWeek => $shiftId) {
            ScheduleTemplateDay::create([
                'template_id' => $template->id,
                'day_of_week' => $dayOfWeek,
                'shift_id' => $shiftId,
            ]);
        }

        return back()->with('success', "Template '{$template->name}' berhasil dibuat.");
    }

    public function update(Request $request, $id)
    {
        $template = ScheduleTemplate::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'days' => 'required|array|size:7',
            'days.*' => 'required|exists:shifts,id',
        ]);

        $template->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // Rebuild days
        $template->days()->delete();
        foreach ($request->days as $dayOfWeek => $shiftId) {
            ScheduleTemplateDay::create([
                'template_id' => $template->id,
                'day_of_week' => $dayOfWeek,
                'shift_id' => $shiftId,
            ]);
        }

        return back()->with('success', "Template '{$template->name}' berhasil diperbarui.");
    }

    public function destroy($id)
    {
        $template = ScheduleTemplate::withCount('employees')->findOrFail($id);

        if ($template->employees_count > 0) {
            return back()->with('error', "Tidak bisa hapus — masih ada {$template->employees_count} karyawan menggunakan template ini.");
        }

        $template->delete();
        return back()->with('success', 'Template berhasil dihapus.');
    }

    /**
     * Sync template assignment — checked = assign, unchecked = lepas dari template ini
     */
    public function assignBulk(Request $request)
    {
        $request->validate([
            'template_id'    => 'required|exists:schedule_templates,id',
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $admin      = Employee::find(session('admin_id'));
        $templateId = (int) $request->template_id;
        $template   = ScheduleTemplate::find($templateId);

        // Semua karyawan aktif di company
        $allIds      = Employee::where('company_id', $admin->company_id)
                                ->where('is_active', true)
                                ->pluck('id')
                                ->toArray();

        $checkedIds  = array_map('intval', $request->input('employee_ids', []));
        $uncheckedIds = array_diff($allIds, $checkedIds);

        // Assign yang di-centang ke template ini
        if (!empty($checkedIds)) {
            Employee::whereIn('id', $checkedIds)
                ->update(['schedule_template_id' => $templateId]);
        }

        // Lepas yang tidak di-centang HANYA jika mereka sedang pakai template ini
        if (!empty($uncheckedIds)) {
            Employee::whereIn('id', $uncheckedIds)
                ->where('schedule_template_id', $templateId)
                ->update(['schedule_template_id' => null]);
        }

        $assigned   = count($checkedIds);
        $unassigned = Employee::whereIn('id', $uncheckedIds)
                               ->whereNull('schedule_template_id')
                               ->count();

        return back()->with('success',
            "{$assigned} karyawan di-assign ke template '{$template->name}'."
            . ($unassigned > 0 ? " {$unassigned} karyawan dilepas dari template ini." : '')
        );
    }
}

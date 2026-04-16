<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $year = $request->year ?? now()->year;

        $holidays = Holiday::where('company_id', $admin->company_id)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        return view('admin.holidays.index', compact('holidays', 'year'));
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'is_national' => 'sometimes|boolean',
        ]);

        Holiday::updateOrCreate(
            [
                'company_id' => $admin->company_id,
                'date' => $request->date,
            ],
            [
                'name' => $request->name,
                'is_national' => $request->boolean('is_national', true),
            ]
        );

        return back()->with('success', "Hari libur '{$request->name}' berhasil ditambahkan.");
    }

    public function importNational(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $year = $request->year ?? now()->year;

        // Indonesian national holidays for the given year
        $nationalHolidays = [
            ["$year-01-01", "Tahun Baru Masehi"],
            ["$year-02-12", "Tahun Baru Imlek"],
            ["$year-03-28", "Isra Mi'raj Nabi Muhammad SAW"],
            ["$year-03-29", "Hari Raya Nyepi"],
            ["$year-03-31", "Hari Raya Idul Fitri"],
            ["$year-04-01", "Hari Raya Idul Fitri"],
            ["$year-04-18", "Wafat Isa Al Masih"],
            ["$year-05-01", "Hari Buruh"],
            ["$year-05-12", "Hari Raya Waisak"],
            ["$year-05-29", "Kenaikan Isa Al Masih"],
            ["$year-06-01", "Hari Lahir Pancasila"],
            ["$year-06-07", "Hari Raya Idul Adha"],
            ["$year-06-27", "Tahun Baru Islam"],
            ["$year-08-17", "Hari Kemerdekaan RI"],
            ["$year-09-05", "Maulid Nabi Muhammad SAW"],
            ["$year-12-25", "Hari Natal"],
        ];

        $count = 0;
        foreach ($nationalHolidays as [$date, $name]) {
            Holiday::updateOrCreate(
                ['company_id' => $admin->company_id, 'date' => $date],
                ['name' => $name, 'is_national' => true]
            );
            $count++;
        }

        return back()->with('success', "$count hari libur nasional tahun $year berhasil diimport.");
    }

    public function destroy($id)
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->delete();
        return back()->with('success', "Hari libur '{$holiday->name}' berhasil dihapus.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Holiday;
use App\Services\OfficialNationalHolidayProvider;
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

    public function importNational(Request $request, OfficialNationalHolidayProvider $holidayProvider)
    {
        $admin = Employee::find(session('admin_id'));
        $year = (int) ($request->year ?? now()->year);

        $nationalHolidays = $holidayProvider->forYear($year);
        if (empty($nationalHolidays)) {
            $availableYears = implode(', ', $holidayProvider->availableYears());
            return back()->with('error', "Data libur nasional resmi tahun $year belum tersedia. Tahun yang tersedia: $availableYears.");
        }

        $count = 0;
        foreach ($nationalHolidays as $date => $name) {
            Holiday::updateOrCreate(
                ['company_id' => $admin->company_id, 'date' => $date],
                ['name' => $name, 'is_national' => true]
            );
            $count++;
        }

        $reference = $holidayProvider->referenceForYear($year);

        return back()->with('success', "$count hari libur nasional tahun $year berhasil diimport dari sumber resmi. Referensi: $reference.");
    }

    public function destroy($id)
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->delete();
        return back()->with('success', "Hari libur '{$holiday->name}' berhasil dihapus.");
    }
}

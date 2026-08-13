<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KpiLevel;
use App\Support\KpiPeriodSnapshot;
use Illuminate\Http\Request;

class KpiLevelController extends Controller
{
    public function index()
    {
        $admin = Employee::find(session('admin_id'));

        $levels = KpiLevel::where('company_id', $admin->company_id)
            ->withCount(['indicators', 'employees'])
            ->orderBy('sort_order')
            ->get();

        $problems = app(KpiPeriodSnapshot::class)->weightProblems($admin->company_id);

        return view('admin.kpi.levels.index', compact('levels', 'problems'));
    }

    public function update(Request $request, KpiLevel $kpiLevel)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiLevel->company_id !== $admin->company_id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'is_assessed' => 'nullable|boolean',
            'weight_excellence' => 'required|numeric|min:0|max:100',
            'weight_contribution' => 'required|numeric|min:0|max:100',
            'weight_leadership' => 'required|numeric|min:0|max:100',
        ]);

        $data['is_assessed'] = $request->boolean('is_assessed');

        $total = $data['weight_excellence'] + $data['weight_contribution'] + $data['weight_leadership'];

        // Level yang tidak dinilai boleh berbobot nol; yang dinilai wajib genap 100.
        if ($data['is_assessed'] && abs($total - 100) >= 0.01) {
            return back()->with('error', "Jumlah bobot kategori harus 100%. Saat ini {$total}%.");
        }

        $kpiLevel->update($data);

        return back()->with('success', "Level {$kpiLevel->code} berhasil diperbarui.");
    }
}

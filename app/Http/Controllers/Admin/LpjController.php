<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Lpj;
use App\Services\LpjExcelExporter;
use Illuminate\Http\Request;

class LpjController extends Controller
{
    public function index(Request $request)
    {
        $query = Lpj::with([
            'employee:id,full_name,photo,department_id',
            'employee.department:id,name',
            'budgetRequest:id,title,total_amount',
        ]);

        $status = $request->get('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_lpj', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($eq) => $eq->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('budgetRequest', fn ($bq) => $bq->where('title', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('month')) {
            $date = \Carbon\Carbon::parse($request->month . '-01');
            $query->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month);
        }

        $lpjs = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.lpj.index', compact('lpjs', 'status'));
    }

    public function show($id)
    {
        $lpj = Lpj::with([
            'employee:id,full_name,photo,department_id,position,job_level',
            'employee.department:id,name',
            'budgetRequest:id,title,total_amount,surat_tugas_no,surat_tugas_date',
            'travelReport:id,destination_city,departure_date,return_date',
            'items.budgetRequestItem',
            'approvalLogs.approver:id,full_name,photo',
        ])->findOrFail($id);

        return view('admin.lpj.show', compact('lpj'));
    }

    public function destroy($id)
    {
        $admin = Employee::find(session('admin_id'));
        if ($admin->role !== 'superadmin') {
            return back()->with('error', 'Hanya superadmin yang dapat menghapus LPJ.');
        }

        $lpj = Lpj::findOrFail($id);

        foreach ($lpj->items as $item) {
            if ($item->bukti_file) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($item->bukti_file);
            }
        }

        $lpj->items()->delete();
        $lpj->approvalLogs()->delete();
        $lpj->delete();

        return redirect()->route('admin.lpj.index')
            ->with('success', 'LPJ berhasil dihapus.');
    }

    public function exportExcel($id)
    {
        return LpjExcelExporter::download(Lpj::findOrFail($id));
    }
}

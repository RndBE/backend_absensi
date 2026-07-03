<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Lpj;
use App\Services\LpjExcelExporter;
use App\Services\LpjExcelImporter;
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

        // Manager: hanya melihat departemennya sendiri.
        if ($dept = \App\Support\AdminDataScope::departmentId(\App\Models\Employee::find(session('admin_id')))) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $dept));
        }

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
        $admin = Employee::find(session('admin_id'));
        $lpj = Lpj::with([
            'employee:id,full_name,photo,department_id,position,job_level',
            'employee.department:id,name',
            'budgetRequest:id,title,total_amount,surat_tugas_no,surat_tugas_date',
            'travelReport:id,destination_city,departure_date,return_date',
            'items.budgetRequestItem',
            'approvalLogs.approver:id,full_name,photo',
        ])->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
          ->findOrFail($id);

        return view('admin.lpj.show', compact('lpj'));
    }

    public function destroy($id)
    {
        $admin = Employee::find(session('admin_id'));
        if ($admin->role !== 'superadmin') {
            return back()->with('error', 'Hanya superadmin yang dapat menghapus LPJ.');
        }

        $lpj = Lpj::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

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
        $admin = Employee::find(session('admin_id'));
        $lpj = Lpj::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        return LpjExcelExporter::download($lpj);
    }

    /**
     * Impor kembali nilai realisasi (reimbursement) dari file Excel hasil ekspor
     * yang sudah diedit, lalu perbarui item LPJ tanpa input manual satu per satu.
     */
    public function importExcel(Request $request, $id)
    {
        $request->validate([
            'lpj_file' => 'required|file|mimes:xlsx|max:10240',
        ], [
            'lpj_file.required' => 'Silakan pilih file Excel hasil export LPJ.',
            'lpj_file.mimes' => 'File harus berformat .xlsx (hasil Export Excel).',
            'lpj_file.max' => 'Ukuran file maksimal 10MB.',
        ]);

        $admin = Employee::find(session('admin_id'));
        $lpj = Lpj::with('items')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        if (! in_array($lpj->status, ['pending', 'in_review'], true)) {
            return back()->with('error', 'Hanya LPJ berstatus menunggu/sedang direview yang bisa diimpor.');
        }

        try {
            $result = LpjExcelImporter::import($lpj, $request->file('lpj_file')->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal impor: '.$e->getMessage());
        }

        $message = "Impor selesai: {$result['updated']} dari {$result['total']} item diperbarui.";
        if (! empty($result['warnings'])) {
            $message .= ' Catatan: '.implode(' ', array_slice($result['warnings'], 0, 5));
        }

        return back()->with($result['updated'] > 0 ? 'success' : 'error', $message);
    }
}

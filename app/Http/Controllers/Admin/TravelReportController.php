<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\Notification;
use App\Models\TravelReport;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TravelReportController extends Controller
{
    public function index(Request $request)
    {
        $query = TravelReport::with([
            'employee:id,full_name,photo,department_id,position',
            'employee.department:id,name',
            'budgetRequest:id,title',
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
                $q->where('destination_city', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($eq) => $eq->where('full_name', 'like', "%{$search}%"));
            });
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.travel-reports.index', compact('reports', 'status'));
    }

    public function create(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $availableRequests = BudgetRequest::whereIn('status', ['approved', 'paid'])
            ->whereDoesntHave('travelReport')
            ->with('employee:id,full_name')
            ->latest()
            ->get();

        $employees = Employee::where('is_active', true)->orderBy('full_name')->get(['id', 'full_name']);

        $selectedRequest = null;
        if ($request->has('budget_request_id')) {
            $selectedRequest = BudgetRequest::with(['employee:id,full_name', 'items'])->find($request->budget_request_id);
        }

        return view('admin.travel-reports.create', compact('availableRequests', 'employees', 'selectedRequest'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'budget_request_id' => 'nullable|exists:budget_requests,id',
            'destination_city' => 'required|string|max:255',
            'departure_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'surat_tugas_no' => 'nullable|string|max:255',
            'surat_tugas_date' => 'nullable|date',
            'purpose' => 'required|string',
            'conclusion' => 'required|string',
            'recommendations' => 'nullable|array',
            'recommendations.*' => 'nullable|string',
            'activities' => 'required|array|min:1',
            'activities.*.date' => 'required|date',
            'activities.*.description' => 'required|string',
            'activities.*.results' => 'nullable|array',
            'activities.*.results.*' => 'nullable|string',
            'activities.*.issues' => 'nullable|string',
            'activities.*.conclusion' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $report = TravelReport::create([
                'employee_id' => $request->employee_id,
                'budget_request_id' => $request->budget_request_id,
                'surat_tugas_no' => $request->surat_tugas_no,
                'surat_tugas_date' => $request->surat_tugas_date,
                'destination_city' => $request->destination_city,
                'departure_date' => $request->departure_date,
                'return_date' => $request->return_date,
                'purpose' => $request->purpose,
                'conclusion' => $request->conclusion,
                'recommendations' => $request->recommendations
                    ? array_values(array_filter($request->recommendations))
                    : null,
                'status' => 'pending',
                'current_step' => 1,
            ]);

            // Save grouped activities
            foreach ($request->activities as $i => $activityData) {
                if (empty($activityData['description'])) {
                    continue;
                }

                $results = isset($activityData['results'])
                    ? array_values(array_filter($activityData['results']))
                    : null;

                $activity = $report->activities()->create([
                    'activity_date' => $activityData['date'],
                    'description' => $activityData['description'],
                    'results' => $results && count($results) ? $results : null,
                    'issues' => $activityData['issues'] ?? null,
                    'conclusion' => $activityData['conclusion'] ?? null,
                    'sort_order' => $i,
                ]);

                // Save documents linked to this activity
                if ($request->hasFile("activities.{$i}.documents")) {
                    foreach ($request->file("activities.{$i}.documents") as $j => $file) {
                        $path = $file->store('travel-report-docs', 'public');
                        $report->documents()->create([
                            'travel_report_activity_id' => $activity->id,
                            'file_path' => $path,
                            'caption' => $request->input("activities.{$i}.document_captions.{$j}"),
                            'activity_date' => $activityData['date'],
                            'sort_order' => $j,
                        ]);
                    }
                }
            }

            DB::commit();

            $employee = Employee::find($request->employee_id);
            $firstApprover = EmployeeApprover::getApproverAt((int) $request->employee_id, 'travel_report', 1);
            if ($employee && $firstApprover) {
                $notification = Notification::create([
                    'employee_id' => $firstApprover->id,
                    'title' => 'Pengajuan LHP Baru',
                    'message' => "{$employee->full_name} mengajukan LHP ke {$report->destination_city}",
                    'type' => 'approval',
                    'reference_type' => TravelReport::class,
                    'reference_id' => $report->id,
                ]);

                FcmService::sendToEmployee($firstApprover, $notification->title, $notification->message, [
                    'type' => 'approval',
                    'reference_type' => 'travel_report',
                    'reference_id' => (string) $report->id,
                ]);
            }

            return redirect()->route('admin.travel-reports.show', $report)
                ->with('success', 'Laporan Hasil Perjalanan berhasil dibuat.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $admin = Employee::find(session('admin_id'));
        $report = TravelReport::with(['employee', 'budgetRequest', 'activities.documents'])
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        if (!in_array($report->status, ['pending', 'in_review'])) {
            return redirect()->route('admin.travel-reports.show', $id)
                ->with('error', 'LHP tidak dapat diedit karena sudah ' . ($report->status === 'approved' ? 'disetujui' : 'ditolak') . '.');
        }

        $availableRequests = BudgetRequest::whereIn('status', ['approved', 'paid'])
            ->where(function ($q) use ($report) {
                $q->whereDoesntHave('travelReport')
                    ->orWhere('id', $report->budget_request_id);
            })
            ->with('employee:id,full_name')
            ->latest()
            ->get();

        return view('admin.travel-reports.edit', compact('report', 'availableRequests'));
    }

    public function update(Request $request, $id)
    {
        $admin = Employee::find(session('admin_id'));
        $report = TravelReport::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        if (!in_array($report->status, ['pending', 'in_review'])) {
            return redirect()->route('admin.travel-reports.show', $id)
                ->with('error', 'LHP tidak dapat diedit karena sudah ' . ($report->status === 'approved' ? 'disetujui' : 'ditolak') . '.');
        }

        $request->validate([
            'destination_city' => 'required|string|max:255',
            'departure_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'surat_tugas_no' => 'nullable|string|max:255',
            'surat_tugas_date' => 'nullable|date',
            'purpose' => 'required|string',
            'conclusion' => 'required|string',
            'recommendations' => 'nullable|array',
            'recommendations.*' => 'nullable|string',
            'activities' => 'required|array|min:1',
            'activities.*.date' => 'required|date',
            'activities.*.description' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $report->update([
                'budget_request_id' => $request->budget_request_id,
                'surat_tugas_no'    => $request->surat_tugas_no,
                'surat_tugas_date'  => $request->surat_tugas_date,
                'destination_city'  => $request->destination_city,
                'departure_date'    => $request->departure_date,
                'return_date'       => $request->return_date,
                'purpose'           => $request->purpose,
                'conclusion'        => $request->conclusion,
                'recommendations'   => $request->recommendations
                    ? array_values(array_filter($request->recommendations))
                    : null,
            ]);

            // Notifikasi approver saat ini bahwa konten LHP telah diperbarui
            $currentApprover = EmployeeApprover::getApproverAt($report->employee_id, 'travel_report', $report->current_step);
            if ($currentApprover) {
                $notif = Notification::create([
                    'employee_id'    => $currentApprover->id,
                    'title'          => 'LHP Diperbarui',
                    'message'        => "{$report->employee->full_name} memperbarui LHP ke {$report->destination_city}.",
                    'type'           => 'approval',
                    'reference_type' => TravelReport::class,
                    'reference_id'   => $report->id,
                ]);
                FcmService::sendToEmployee($currentApprover, $notif->title, $notif->message, [
                    'type'           => 'approval',
                    'reference_type' => 'travel_report',
                    'reference_id'   => (string) $report->id,
                ]);
            }

            // Replace activities & documents
            foreach ($report->documents as $doc) {
                Storage::disk('public')->delete($doc->file_path);
            }
            $report->documents()->delete();
            $report->activities()->delete();

            foreach ($request->activities as $i => $activityData) {
                if (empty($activityData['description'])) {
                    continue;
                }

                $results = isset($activityData['results'])
                    ? array_values(array_filter($activityData['results']))
                    : null;

                $activity = $report->activities()->create([
                    'activity_date' => $activityData['date'],
                    'description' => $activityData['description'],
                    'results' => $results && count($results) ? $results : null,
                    'issues' => $activityData['issues'] ?? null,
                    'conclusion' => $activityData['conclusion'] ?? null,
                    'sort_order' => $i,
                ]);

                if ($request->hasFile("activities.{$i}.documents")) {
                    foreach ($request->file("activities.{$i}.documents") as $j => $file) {
                        $path = $file->store('travel-report-docs', 'public');
                        $report->documents()->create([
                            'travel_report_activity_id' => $activity->id,
                            'file_path' => $path,
                            'caption' => $request->input("activities.{$i}.document_captions.{$j}"),
                            'activity_date' => $activityData['date'],
                            'sort_order' => $j,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('admin.travel-reports.show', $id)
                ->with('success', 'LHP berhasil diperbarui.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $admin = Employee::find(session('admin_id'));
        $report = TravelReport::with([
            'employee:id,full_name,photo,department_id,position,job_level',
            'employee.department:id,name',
            'budgetRequest:id,title,total_amount',
            'activities.documents',
            'documents',
            'approvalLogs.approver:id,full_name,photo',
        ])->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
          ->findOrFail($id);

        return view('admin.travel-reports.show', compact('report'));
    }

    public function print($id)
    {
        $admin = Employee::find(session('admin_id'));
        $report = TravelReport::with([
            'employee:id,full_name,photo,signature,department_id,position,job_level',
            'employee.department:id,name',
            'budgetRequest:id,title',
            'activities.documents',
            'documents',
            'approvalLogs.approver:id,full_name,signature',
        ])->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
          ->findOrFail($id);

        return view('admin.travel-reports.print', compact('report'));
    }

    public function destroy($id)
    {
        $admin = Employee::find(session('admin_id'));
        if (! in_array($admin->role, ['superadmin', 'admin'])) {
            return back()->with('error', 'Tidak memiliki izin untuk menghapus LHP.');
        }

        $report = TravelReport::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        foreach ($report->documents as $doc) {
            Storage::disk('public')->delete($doc->file_path);
        }

        $report->activities()->delete();
        $report->documents()->delete();
        $report->approvalLogs()->delete();
        $report->delete();

        return redirect()->route('admin.travel-reports.index')
            ->with('success', 'Laporan Hasil Perjalanan berhasil dihapus.');
    }
}

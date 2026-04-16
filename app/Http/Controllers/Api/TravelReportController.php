<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\TravelReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TravelReportController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user();

        $reports = TravelReport::where('employee_id', $employee->id)
            ->with([
                'budgetRequest:id,title,total_amount',
                'activities:id,travel_report_id,activity_date,description',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $reports]);
    }

    public function show(Request $request, $id)
    {
        $report = TravelReport::with([
            'employee:id,full_name,photo,department_id,position',
            'employee.department:id,name',
            'budgetRequest:id,title,total_amount',
            'activities.documents',
            'documents',
            'approvalLogs.approver:id,full_name,photo',
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $report]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'budget_request_id' => 'nullable|exists:budget_requests,id',
            'destination_city' => 'required|string|max:255',
            'departure_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'surat_tugas_no' => 'nullable|string|max:255',
            'surat_tugas_date' => 'nullable|date',
            'purpose' => 'required|string',
            'conclusion' => 'required|string',
            'recommendations' => 'nullable', // JSON string from mobile
            'activities' => 'required', // JSON string from mobile
        ]);

        $employee = $request->user();

        DB::beginTransaction();
        try {
            $recommendations = $request->recommendations;
            if (is_string($recommendations)) {
                $recommendations = json_decode($recommendations, true);
            }
            $recommendations = $recommendations ? array_values(array_filter($recommendations)) : null;

            $activitiesData = $request->activities;
            if (is_string($activitiesData)) {
                $activitiesData = json_decode($activitiesData, true);
            }

            $report = TravelReport::create([
                'employee_id' => $employee->id,
                'budget_request_id' => $request->budget_request_id,
                'surat_tugas_no' => $request->surat_tugas_no,
                'surat_tugas_date' => $request->surat_tugas_date,
                'destination_city' => $request->destination_city,
                'departure_date' => $request->departure_date,
                'return_date' => $request->return_date,
                'purpose' => $request->purpose,
                'conclusion' => $request->conclusion,
                'recommendations' => $recommendations,
                'status' => 'pending',
                'current_step' => 1,
            ]);

            // Save activities
            if ($activitiesData && is_array($activitiesData)) {
                foreach ($activitiesData as $i => $actData) {
                    if (empty($actData['description'])) continue;

                    $results = isset($actData['results'])
                        ? array_values(array_filter($actData['results'] ?? []))
                        : null;

                    $activity = $report->activities()->create([
                        'activity_date' => $actData['date'],
                        'description' => $actData['description'],
                        'results' => $results && count($results) ? $results : null,
                        'issues' => $actData['issues'] ?? null,
                        'conclusion' => $actData['conclusion'] ?? null,
                        'sort_order' => $i,
                    ]);

                    // Activity documents from multipart
                    if ($request->hasFile("activity_documents_{$i}")) {
                        foreach ($request->file("activity_documents_{$i}") as $j => $file) {
                            $path = $file->store('travel-report-docs', 'public');
                            $report->documents()->create([
                                'travel_report_activity_id' => $activity->id,
                                'file_path' => $path,
                                'caption' => $request->input("activity_captions_{$i}.{$j}"),
                                'activity_date' => $actData['date'],
                                'sort_order' => $j,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'LHP berhasil dibuat',
                'data' => $report->load('activities'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat LHP: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get budget requests available to link with LHP.
     */
    public function availableRequests(Request $request)
    {
        $employee = $request->user();

        $requests = BudgetRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'paid'])
            ->whereDoesntHave('travelReport')
            ->latest()
            ->get(['id', 'title', 'total_amount', 'surat_tugas_no', 'surat_tugas_date']);

        return response()->json(['success' => true, 'data' => $requests]);
    }
}

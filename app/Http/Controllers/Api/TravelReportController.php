<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\EmployeeApprover;
use App\Models\Notification;
use App\Models\TravelReport;
use App\Models\TravelZone;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            'budgetRequest:id,title,total_amount,distance_km,travel_zone_id',
            'budgetRequest.travelZone',
            'travelZone',
            'activities.documents',
            'documents',
            'approvalLogs.approver:id,full_name,photo',
        ])->findOrFail($id);
        $report->setAttribute('can_edit', $this->canEditTravelReport($report));

        // Perbandingan uang makan budget vs realisasi
        $mealComparison = null;
        if ($report->travelZone && $report->duration_days > 0) {
            $actualMeal = $report->meal_allowance_total;
            $budgetMeal = null;
            if ($report->budgetRequest?->travelZone) {
                $budgetMeal = (float) $report->budgetRequest->travelZone->meal_allowance
                    * $report->duration_days;
            }
            $mealComparison = [
                'actual_meal'       => $actualMeal,
                'budget_meal'       => $budgetMeal,
                'selisih'           => $budgetMeal !== null ? $actualMeal - $budgetMeal : null,
                'zone_name'         => $report->travelZone->name,
                'meal_per_day'      => (float) $report->travelZone->meal_allowance,
                'duration_days'     => $report->duration_days,
            ];
        }
        $report->setAttribute('meal_comparison', $mealComparison);

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
            'distance_km' => 'nullable|integer|min:0',
            'purpose' => 'required|string',
            'conclusion' => 'required|string',
            'recommendations' => 'nullable',
            'activities' => 'required',
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

            $distanceKm = $request->filled('distance_km') ? (int) $request->distance_km : null;
            $canStoreDistance = Schema::hasColumn('travel_reports', 'distance_km');
            $canStoreTravelZone = Schema::hasColumn('travel_reports', 'travel_zone_id');
            $travelZone = $distanceKm !== null && $canStoreTravelZone && Schema::hasTable('travel_zones')
                ? TravelZone::findByKm($distanceKm)
                : null;

            $payload = [
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
            ];

            if ($canStoreDistance) {
                $payload['distance_km'] = $distanceKm;
            }
            if ($canStoreTravelZone) {
                $payload['travel_zone_id'] = $travelZone?->id;
            }

            $report = TravelReport::create($payload);

            // Save activities
            if ($activitiesData && is_array($activitiesData)) {
                foreach ($activitiesData as $i => $actData) {
                    if (empty($actData['description'])) {
                        continue;
                    }

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

            $firstApprover = EmployeeApprover::getApproverAt($employee->id, 'travel_report', 1);
            if ($firstApprover) {
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

            return response()->json([
                'success' => true,
                'message' => 'LHP berhasil dibuat',
                'data' => $report->load('activities'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat LHP: '.$e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $employee = $request->user();
        $report = TravelReport::where('id', $id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        if (! $this->canEditTravelReport($report)) {
            $reason = match ($report->status) {
                'approved' => 'sudah disetujui',
                'rejected' => 'sudah ditolak',
                default    => 'sedang dalam proses persetujuan lanjutan',
            };
            return response()->json([
                'success' => false,
                'message' => "LHP tidak dapat diedit karena $reason.",
            ], 403);
        }

        $request->validate([
            'destination_city' => 'required|string|max:255',
            'departure_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'surat_tugas_no' => 'nullable|string|max:255',
            'surat_tugas_date' => 'nullable|date',
            'distance_km' => 'nullable|integer|min:0',
            'purpose' => 'required|string',
            'conclusion' => 'required|string',
            'recommendations' => 'nullable',
            'activities' => 'required',
        ]);

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

            $canStoreDistance = Schema::hasColumn('travel_reports', 'distance_km');
            $canStoreTravelZone = Schema::hasColumn('travel_reports', 'travel_zone_id');
            $distanceKm = $request->filled('distance_km')
                ? (int) $request->distance_km
                : ($canStoreDistance ? $report->distance_km : null);
            $travelZone = $distanceKm !== null && $canStoreTravelZone && Schema::hasTable('travel_zones')
                ? TravelZone::findByKm($distanceKm)
                : null;

            $payload = [
                'budget_request_id' => $request->budget_request_id,
                'surat_tugas_no' => $request->surat_tugas_no,
                'surat_tugas_date' => $request->surat_tugas_date,
                'destination_city' => $request->destination_city,
                'departure_date' => $request->departure_date,
                'return_date' => $request->return_date,
                'purpose' => $request->purpose,
                'conclusion' => $request->conclusion,
                'recommendations' => $recommendations,
            ];

            if ($canStoreDistance) {
                $payload['distance_km'] = $distanceKm;
            }
            if ($canStoreTravelZone) {
                $payload['travel_zone_id'] = $travelZone?->id;
            }

            $report->update($payload);

            // Notifikasi approver saat ini bahwa konten LHP telah diperbarui
            $currentApprover = EmployeeApprover::getApproverAt($employee->id, 'travel_report', $report->current_step);
            if ($currentApprover) {
                $notif = Notification::create([
                    'employee_id' => $currentApprover->id,
                    'title' => 'LHP Diperbarui',
                    'message' => "{$employee->full_name} memperbarui LHP ke {$report->destination_city}.",
                    'type' => 'approval',
                    'reference_type' => TravelReport::class,
                    'reference_id' => $report->id,
                ]);
                FcmService::sendToEmployee($currentApprover, $notif->title, $notif->message, [
                    'type' => 'approval',
                    'reference_type' => 'travel_report',
                    'reference_id' => (string) $report->id,
                ]);
            }

            // Replace all activities and documents
            $report->documents()->delete();
            $report->activities()->delete();

            if ($activitiesData && is_array($activitiesData)) {
                foreach ($activitiesData as $i => $actData) {
                    if (empty($actData['description'])) {
                        continue;
                    }

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
                'message' => 'LHP berhasil diperbarui',
                'data' => $report->load('activities'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui LHP: '.$e->getMessage(),
            ], 500);
        }
    }

    private function canEditTravelReport(TravelReport $report): bool
    {
        if (! in_array($report->status, ['pending', 'in_review'], true)) {
            return false;
        }

        $hseStep = EmployeeApprover::where('employee_id', $report->employee_id)
            ->where('request_type', 'travel_report')
            ->whereHas('approver', fn ($q) => $q->where('position', 'like', '%HSE%'))
            ->min('step_order');

        if ($hseStep === null) {
            return true;
        }

        return (int) $report->current_step === (int) $hseStep;
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

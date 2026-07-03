<?php

namespace App\Http\Controllers\Api\Tessa;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\EmployeeApprover;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\ScheduleAssignment;
use App\Models\Setting;
use App\Models\TravelReport;
use App\Services\LhpReminderService;
use App\Services\LpjReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Endpoint SISTEM (bukan per-user): daftar reminder yang jatuh tempo, agar Tessa
 * mengirimkannya lewat WhatsApp. Dijaga service key (TESSA_API_KEY) karena ini fungsi
 * broadcast/sistem — bukan aksi milik satu karyawan. Payroll tidak tersentuh.
 *
 * Read-only, berbasis state (LPJ/LHP belum dibuat; belum clock-in). Tessa cukup panggil
 * sekali pada jam yang diinginkan lalu kirim ke tiap nomor.
 */
class TessaReminderController extends Controller
{
    public function due(Request $request)
    {
        $request->validate([
            'type' => 'required|in:clockin,lhp,lpj',
            'date' => 'nullable|date',
        ]);

        $type = $request->query('type');
        $date = $request->query('date') ? Carbon::parse($request->query('date'))->startOfDay() : now()->startOfDay();
        $companyId = $this->companyScope();

        $recipients = match ($type) {
            'lpj' => $this->fromReminderItems(LpjReminderService::dueForDate($date, $companyId)),
            'lhp' => $this->fromReminderItems(LhpReminderService::dueForDate($date, $companyId)),
            'clockin' => $this->clockinDue($date, $companyId),
        };

        // Hanya yang punya nomor HP yang bisa dikirim WhatsApp; sisanya dilaporkan.
        $withPhone = $recipients->filter(fn ($r) => filled($r['phone']))->values();
        $noPhone = $recipients->count() - $withPhone->count();

        return response()->json([
            'success' => true,
            'type' => $type,
            'date' => $date->toDateString(),
            'count' => $withPhone->count(),
            'skipped_no_phone' => $noPhone,
            'reminders' => $withPhone->all(),
        ]);
    }

    /**
     * Pengajuan yang menunggu persetujuan, beserta APPROVER STEP AKTIF-nya (nama + nomor +
     * pesan siap kirim), agar Tessa mem-WA approver yang tepat. Service key.
     *
     * Dedup: kirim ?since=<ISO waktu poll terakhir> → hanya yang berubah sejak itu
     * (pengajuan baru + yang baru saja maju ke step berikut → approver baru).
     */
    public function pendingApprovals(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:leave,overtime,attendance,budget,travel_report',
            'since' => 'nullable|date',
        ]);

        $companyId = $this->companyScope();
        $since = $request->query('since') ? Carbon::parse($request->query('since')) : null;
        $filter = $request->query('type');

        // type Tessa => [model, request_type employee_approvers, label]
        $types = [
            'leave' => [LeaveRequest::class, 'leave', 'Cuti'],
            'overtime' => [OvertimeRequest::class, 'overtime', 'Lembur'],
            'attendance' => [AttendanceRequest::class, 'attendance', 'Koreksi Presensi'],
            'budget' => [BudgetRequest::class, 'budget', 'Anggaran'],
            'travel_report' => [TravelReport::class, 'travel_report', 'LHP'],
        ];

        $rows = collect();

        foreach ($types as $key => [$modelClass, $requestType, $label]) {
            if ($filter && $filter !== $key) {
                continue;
            }

            $items = $modelClass::with('employee:id,full_name,company_id')
                ->whereIn('status', ['pending', 'in_review'])
                ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
                ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
                ->get();

            foreach ($items as $item) {
                $step = (int) ($item->current_step ?? 1);
                $approver = EmployeeApprover::getApproverAt($item->employee_id, $requestType, $step);
                if (! $approver) {
                    continue; // tak ada approver di step ini → lewati
                }

                $employeeName = $item->employee?->full_name ?? '-';
                $rows->push([
                    'type' => $key,
                    'id' => $item->id,
                    'employee' => $employeeName,
                    'current_step' => $step,
                    'approver' => [
                        'id' => $approver->id,
                        'name' => $approver->full_name,
                        'phone' => $approver->phone,
                    ],
                    'message' => "Ada pengajuan {$label} dari {$employeeName} menunggu persetujuan Anda (step {$step}). Balas \"approve\" untuk menyetujui atau \"tolak\" untuk menolak.",
                ]);
            }
        }

        $withPhone = $rows->filter(fn ($r) => filled($r['approver']['phone']))->values();

        return response()->json([
            'success' => true,
            'count' => $withPhone->count(),
            'skipped_no_phone' => $rows->count() - $withPhone->count(),
            'pending' => $withPhone->all(),
        ]);
    }

    /**
     * Pengajuan yang sudah final (approved/rejected), beserta nomor pengaju dan pesan
     * siap kirim, agar Tessa bisa memberi kabar via WhatsApp ke pengaju.
     */
    public function approvalResults(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:leave,overtime,attendance,budget,travel_report',
            'status' => 'nullable|in:approved,rejected',
            'since' => 'nullable|date',
        ]);

        $companyId = $this->companyScope();
        $since = $request->query('since') ? Carbon::parse($request->query('since')) : null;
        $filter = $request->query('type');
        $statusFilter = $request->query('status');

        $types = [
            'leave' => [LeaveRequest::class, 'Cuti'],
            'overtime' => [OvertimeRequest::class, 'Lembur'],
            'attendance' => [AttendanceRequest::class, 'Koreksi Presensi'],
            'budget' => [BudgetRequest::class, 'Anggaran'],
            'travel_report' => [TravelReport::class, 'LHP'],
        ];

        $rows = collect();

        foreach ($types as $key => [$modelClass, $label]) {
            if ($filter && $filter !== $key) {
                continue;
            }

            $items = $modelClass::with('employee:id,full_name,phone,company_id')
                ->whereIn('status', $statusFilter ? [$statusFilter] : ['approved', 'rejected'])
                ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
                ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
                ->orderBy('updated_at')
                ->get();

            foreach ($items as $item) {
                $employeeName = $item->employee?->full_name ?? '-';
                $status = (string) $item->status;
                $decision = $status === 'approved' ? 'disetujui' : 'ditolak';

                $rows->push([
                    'type' => $key,
                    'id' => $item->id,
                    'status' => $status,
                    'employee' => [
                        'id' => $item->employee?->id,
                        'name' => $employeeName,
                        'phone' => $item->employee?->phone,
                    ],
                    'title' => "Pengajuan {$label} ".ucfirst($decision),
                    'message' => "Halo {$employeeName}, pengajuan {$label} Anda telah {$decision}.",
                    'updated_at' => optional($item->updated_at)->toISOString(),
                ]);
            }
        }

        $withPhone = $rows->filter(fn ($r) => filled($r['employee']['phone']))->values();

        return response()->json([
            'success' => true,
            'count' => $withPhone->count(),
            'skipped_no_phone' => $rows->count() - $withPhone->count(),
            'results' => $withPhone->all(),
        ]);
    }

    /** Ubah item {employee,title,message} dari service jadi baris siap-kirim. */
    private function fromReminderItems(\Illuminate\Support\Collection $items): \Illuminate\Support\Collection
    {
        return $items->map(fn (array $item) => [
            'employee_id' => $item['employee']->id,
            'name' => $item['employee']->full_name,
            'phone' => $item['employee']->phone,
            'title' => $item['title'],
            'message' => $item['message'],
        ])->values();
    }

    /** Karyawan terjadwal kerja hari ini yang belum clock-in (bila reminder aktif). */
    private function clockinDue(Carbon $date, ?int $companyId): \Illuminate\Support\Collection
    {
        if (Setting::getValue('clockin_reminder_enabled', '0') !== '1') {
            return collect();
        }

        // Sudah clock-in hari ini → dikeluarkan.
        $clockedIn = Attendance::whereDate('date', $date)
            ->whereNotNull('clock_in')
            ->pluck('employee_id')
            ->flip();

        return ScheduleAssignment::query()
            ->whereDate('date', $date)
            ->whereHas('shift', fn ($q) => $q->where('is_off', false)) // hari kerja, bukan libur
            ->whereHas('employee', fn ($q) => $q->where('is_active', true)
                ->when($companyId, fn ($e) => $e->where('company_id', $companyId)))
            ->with('employee:id,full_name,phone,company_id')
            ->get()
            ->filter(fn ($a) => $a->employee && ! $clockedIn->has($a->employee_id))
            ->unique('employee_id')
            ->map(fn ($a) => [
                'employee_id' => $a->employee->id,
                'name' => $a->employee->full_name,
                'phone' => $a->employee->phone,
                'title' => 'Pengingat Clock-In',
                'message' => "Halo {$a->employee->full_name}, Anda belum melakukan clock-in hari ini. Jangan lupa presensi ya.",
            ])
            ->values();
    }

    private function companyScope(): ?int
    {
        $scope = config('services.tessa.company_id');

        return $scope ? (int) $scope : null;
    }
}

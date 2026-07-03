<?php

namespace App\Http\Controllers\Api\Tessa;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ScheduleAssignment;
use App\Models\Setting;
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

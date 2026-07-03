<?php

namespace App\Http\Controllers\Api\Tessa;

use App\Http\Controllers\Api\AttendanceRequestController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\Tessa\Concerns\EnforcesHrisRole;
use App\Http\Controllers\Api\TravelReportController;
use App\Http\Controllers\Controller;
use App\Models\DataChangeRequest;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateDay;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Aksi tulis untuk AI kantor "Tessa" — di luar baca, notifikasi & jadwal harian
 * (yang ada di TessaController).
 *
 * Prinsip keamanan:
 * - Aksi sensitif (approve/reject, ubah data karyawan) memakai pengaman manusia:
 *   approve mendukung mode preview (dry_run), perubahan data lewat PENGAJUAN yang
 *   harus disetujui superadmin di website.
 * - Tessa bertindak sebagai "tangan" seorang superadmin (aktor) — bukan identitas
 *   tanpa pemilik. Semua approve tercatat acted_by = superadmin + catatan "via Tessa".
 * - SELALU terkunci: payroll/gaji, dan field role/password/manager_id/approver_id/company_id.
 */
class TessaActionController extends Controller
{
    use EnforcesHrisRole;

    /** Tipe pengajuan yang boleh di-approve Tessa (data-change SENGAJA tidak ada — wajib via web). */
    private const APPROVAL_TYPES = ['leave', 'overtime', 'attendance', 'budget', 'travel_report'];

    /** Field karyawan yang boleh diubah Tessa (lewat pengajuan). role/password/dll TIDAK termasuk. */
    private const EDITABLE_FIELDS = [
        // Kontak
        'phone', 'email', 'residential_address', 'ktp_address', 'postal_code',
        // Kepegawaian
        'position', 'department_id', 'job_level', 'employment_status',
        'join_date', 'contract_start_date', 'contract_end_date',
        // Identitas
        'full_name', 'birth_place', 'birth_date', 'gender', 'marital_status', 'blood_type', 'religion',
        // Pajak / bank
        'nik', 'npwp_15', 'npwp_16', 'bpjs_tk', 'bpjs_kesehatan', 'bank_account', 'bank_name', 'ptkp',
    ];

    // =====================================================================
    // Approve / reject pengajuan (reuse Api\ApprovalController)
    // =====================================================================

    public function approve(Request $request, string $type, $id)
    {
        return $this->decide($request, $type, $id, 'approve');
    }

    public function reject(Request $request, string $type, $id)
    {
        return $this->decide($request, $type, $id, 'reject');
    }

    private function decide(Request $request, string $type, $id, string $action)
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
            'dry_run' => 'nullable|boolean',
        ]);

        // TIDAK ada gate permission di sini. Otorisasi = aturan approver per-step yang
        // ditegakkan Api\ApprovalController (hanya approver step aktif / superadmin yang
        // boleh, else 403). Ini menyamai aplikasi mobile — approver ber-role employee
        // (mis. team lead) pun tetap bisa approve lewat Tessa.

        if ($type === 'data-change') {
            return $this->fail('Perubahan data karyawan harus disetujui superadmin lewat website, bukan via Tessa.', 422);
        }

        $modelClass = $this->approvalModel($type);
        if (! $modelClass) {
            return $this->fail("Tipe '{$type}' tidak valid. Pilih: ".implode(', ', self::APPROVAL_TYPES), 404);
        }

        $companyId = $this->companyId($request);
        $item = $modelClass::with('employee:id,full_name,company_id')->find($id);
        if (! $item) {
            return $this->fail('Pengajuan tidak ditemukan.', 404);
        }
        if ($companyId && $item->employee?->company_id !== $companyId) {
            return $this->fail('Pengajuan di luar cakupan perusahaan Tessa.', 403);
        }
        if (! in_array($item->status, ['pending', 'in_review'], true)) {
            return $this->fail("Pengajuan sudah diproses (status: {$item->status}).", 422);
        }

        $actor = $this->actor();

        // Mode preview: tampilkan rencana tanpa mengeksekusi.
        if ($request->boolean('dry_run')) {
            return response()->json([
                'success' => true,
                'dry_run' => true,
                'message' => 'PREVIEW: belum dieksekusi.',
                'plan' => [
                    'action' => $action,
                    'type' => $type,
                    'request_id' => $item->id,
                    'employee' => $item->employee?->full_name,
                    'current_step' => $item->current_step,
                    'as_user' => $actor?->full_name,
                ],
            ]);
        }

        // Delegasikan ke logika approval asli, bertindak sebagai USER yang login (aktor),
        // dengan catatan "via Tessa" agar jejaknya jujur.
        $notes = trim(($request->input('notes') ? $request->input('notes').' ' : '').'(via Tessa AI)');
        $sub = $this->actingAs($actor, ['notes' => $notes]);

        return app(\App\Http\Controllers\Api\ApprovalController::class)->{$action}($sub, $type, $id);
    }

    // =====================================================================
    // Ubah data karyawan — via PENGAJUAN (disetujui superadmin di website)
    // =====================================================================

    /**
     * Body: { employee|employee_code|employee_id, changes: {field: value, ...} }
     * Tiap field jadi satu DataChangeRequest; superadmin menyetujui di website.
     */
    public function requestDataChange(Request $request)
    {
        $request->validate([
            'changes' => 'required|array|min:1',
            'employee_id' => 'nullable|integer',
            'employee_code' => 'nullable|string',
            'employee' => 'nullable|string',
        ]);

        $this->requirePermission('employees.update'); // usul perubahan data karyawan: admin saja

        $companyId = $this->companyId($request);

        $employee = $this->resolveEmployee($request->only(['employee_id', 'employee_code', 'employee']), $companyId);
        if (is_string($employee)) {
            return $this->fail($employee, 422);
        }

        $results = [];
        $created = 0;

        foreach ($request->input('changes') as $field => $value) {
            if (! in_array($field, self::EDITABLE_FIELDS, true)) {
                $results[] = ['field' => $field, 'success' => false, 'error' => 'Field tidak diizinkan diubah via Tessa.'];

                continue;
            }

            DataChangeRequest::create([
                'employee_id' => $employee->id,
                'field_name' => $field,
                'old_value' => (string) ($employee->{$field} ?? ''),
                'new_value' => (string) $value,
                'status' => 'pending',
            ]);

            $results[] = ['field' => $field, 'success' => true, 'old' => (string) ($employee->{$field} ?? ''), 'new' => (string) $value];
            $created++;
        }

        if ($created > 0) {
            $this->notifySuperadmins($companyId, 'Pengajuan Perubahan Data (via Tessa)',
                "Tessa mengajukan {$created} perubahan data untuk {$employee->full_name}. Menunggu persetujuan Anda.");
        }

        return response()->json([
            'success' => $created > 0,
            'message' => "{$created} usulan perubahan dibuat untuk {$employee->full_name}; menunggu persetujuan superadmin.",
            'employee' => $employee->full_name,
            'created' => $created,
            'results' => $results,
        ]);
    }

    // =====================================================================
    // Master jadwal: shift & template
    // =====================================================================

    public function createShift(Request $request)
    {
        $this->requirePermission('schedule.master.manage');
        $data = $this->validateShift($request);
        $companyId = $this->actor()->company_id;
        $isOff = (bool) ($data['is_off'] ?? false);
        $autoOt = ! $isOff && (bool) ($data['auto_overtime'] ?? false);

        $shift = Shift::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'start_time' => $isOff ? null : ($data['start_time'] ?? null),
            'end_time' => $isOff ? null : ($data['end_time'] ?? null),
            'color' => $data['color'] ?? '#3B82F6',
            'is_off' => $isOff,
            'is_overnight' => ! $isOff && (bool) ($data['is_overnight'] ?? false),
            'sort_order' => (int) Shift::where('company_id', $companyId)->max('sort_order') + 1,
            'work_hours' => $autoOt ? ($data['work_hours'] ?? null) : null,
            'auto_overtime' => $autoOt,
        ]);

        return response()->json(['success' => true, 'message' => "Shift '{$shift->name}' dibuat.", 'shift_id' => $shift->id]);
    }

    public function updateShift(Request $request, $id)
    {
        $this->requirePermission('schedule.master.manage');
        $companyId = $this->companyId($request);
        $shift = Shift::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->find($id);
        if (! $shift) {
            return $this->fail('Shift tidak ditemukan.', 404);
        }

        $data = $this->validateShift($request);
        $isOff = (bool) ($data['is_off'] ?? $shift->is_off);
        $autoOt = ! $isOff && (bool) ($data['auto_overtime'] ?? $shift->auto_overtime);

        $shift->update([
            'name' => $data['name'],
            'start_time' => $isOff ? null : ($data['start_time'] ?? $shift->start_time),
            'end_time' => $isOff ? null : ($data['end_time'] ?? $shift->end_time),
            'color' => $data['color'] ?? $shift->color,
            'is_off' => $isOff,
            'is_overnight' => ! $isOff && (bool) ($data['is_overnight'] ?? $shift->is_overnight),
            'work_hours' => $autoOt ? ($data['work_hours'] ?? $shift->work_hours) : null,
            'auto_overtime' => $autoOt,
        ]);

        return response()->json(['success' => true, 'message' => "Shift '{$shift->name}' diperbarui.", 'shift_id' => $shift->id]);
    }

    /**
     * Buat template jadwal mingguan.
     * Body: { name, description?, days: [ {day_of_week:1..7, shift|shift_id}, ... ] }
     */
    public function createTemplate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'days' => 'required|array|min:1|max:7',
            'days.*.day_of_week' => 'required|integer|min:1|max:7',
            'days.*.shift' => 'nullable|string',
            'days.*.shift_id' => 'nullable|integer',
        ]);

        $this->requirePermission('schedule.master.manage');
        $companyId = $this->actor()->company_id;

        $resolved = [];
        foreach ($request->input('days') as $day) {
            $shift = $this->resolveShift($day, $companyId);
            if (is_string($shift)) {
                return $this->fail("Hari {$day['day_of_week']}: {$shift}", 422);
            }
            $resolved[(int) $day['day_of_week']] = $shift->id;
        }

        $template = DB::transaction(function () use ($request, $companyId, $resolved) {
            $template = ScheduleTemplate::create([
                'company_id' => $companyId,
                'name' => $request->input('name'),
                'description' => $request->input('description'),
            ]);
            foreach ($resolved as $dow => $shiftId) {
                ScheduleTemplateDay::create(['template_id' => $template->id, 'day_of_week' => $dow, 'shift_id' => $shiftId]);
            }

            return $template;
        });

        return response()->json([
            'success' => true,
            'message' => "Template '{$template->name}' dibuat dengan ".count($resolved).' hari.',
            'template_id' => $template->id,
        ]);
    }

    /**
     * Tempelkan template ke karyawan.
     * Body: { template_id, employees: [ {employee|employee_code|employee_id}, ... ] }
     */
    public function assignTemplate(Request $request)
    {
        $request->validate([
            'template_id' => 'required|integer',
            'employees' => 'required|array|min:1|max:500',
        ]);

        $this->requirePermission('schedule.master.manage');
        $companyId = $this->companyId($request);
        $template = ScheduleTemplate::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->find($request->input('template_id'));
        if (! $template) {
            return $this->fail('Template tidak ditemukan.', 404);
        }

        $results = [];
        $assigned = 0;
        foreach ($request->input('employees') as $i => $ref) {
            $employee = $this->resolveEmployee((array) $ref, $companyId);
            if (is_string($employee)) {
                $results[] = ['index' => $i, 'success' => false, 'error' => $employee];

                continue;
            }
            // Pastikan karyawan & template satu perusahaan.
            if ($employee->company_id !== $template->company_id) {
                $results[] = ['index' => $i, 'success' => false, 'error' => 'Karyawan beda perusahaan dengan template.'];

                continue;
            }
            $employee->update(['schedule_template_id' => $template->id]);
            $results[] = ['index' => $i, 'success' => true, 'employee' => $employee->full_name];
            $assigned++;
        }

        return response()->json([
            'success' => $assigned > 0,
            'message' => "Template '{$template->name}' ditempelkan ke {$assigned} karyawan.",
            'assigned' => $assigned,
            'results' => $results,
        ]);
    }

    // =====================================================================
    // Buat pengajuan atas nama karyawan (reuse Api store)
    // =====================================================================

    /**
     * Body: { employee|employee_code|employee_id, ...field sesuai jenis pengajuan }
     * type: leave | overtime | attendance | budget | travel-report
     */
    public function createRequest(Request $request, string $type)
    {
        $controllers = [
            'leave' => LeaveController::class,
            'overtime' => OvertimeController::class,
            'attendance' => AttendanceRequestController::class,
            'budget' => BudgetController::class,
            'travel-report' => TravelReportController::class,
        ];

        if (! isset($controllers[$type])) {
            return $this->fail("Jenis '{$type}' tidak didukung. Pilih: ".implode(', ', array_keys($controllers)), 404);
        }

        $companyId = $this->companyId($request);
        $ref = $request->only(['employee_id', 'employee_code', 'employee']);
        $hasRef = filled($ref['employee_id'] ?? null) || filled($ref['employee_code'] ?? null) || filled($ref['employee'] ?? null);

        // Default: pengajuan untuk DIRI SENDIRI (self-service, boleh role employee).
        $employee = $hasRef ? $this->resolveEmployee($ref, $companyId) : $this->actor();
        if (is_string($employee)) {
            return $this->fail($employee, 422);
        }

        // Membuat pengajuan atas nama ORANG LAIN = aksi admin, butuh permission sesuai jenis.
        if ((int) $employee->id !== (int) $this->actor()?->id) {
            $this->requirePermission($this->createPermissionFor($type));
        }

        // Delegasikan ke store() asli, bertindak SEBAGAI karyawan tsb (employee_id = dia).
        $input = $request->except(['employee_id', 'employee_code', 'employee', 'as_employee_id']);
        $input = $this->tagViaTessa($input, $type); // jejak sumber "via Tessa"
        $sub = $this->actingAs($employee, $input);

        return app($controllers[$type])->store($sub);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Tandai jejak "via Tessa" pada field teks yang sesuai per jenis, agar approver/admin
     * tahu sumber pengajuan. Non-destruktif: hanya menambah suffix (atau isi bila kosong).
     */
    private function tagViaTessa(array $input, string $type): array
    {
        $field = match ($type) {
            'budget' => 'description',
            'travel-report' => 'purpose',
            default => 'reason', // leave, overtime, attendance
        };

        $existing = trim((string) ($input[$field] ?? ''));
        $input[$field] = $existing === '' ? 'Diajukan via Tessa' : $existing.' (via Tessa)';

        return $input;
    }

    /** Permission admin yang dibutuhkan untuk membuat pengajuan atas nama orang lain. */
    private function createPermissionFor(string $type): string
    {
        return [
            'leave' => 'leaves.create',
            'overtime' => 'attendance.manage',
            'attendance' => 'attendance.manage',
            'budget' => 'budget.manage',
            'travel-report' => 'travel.reports.manage',
        ][$type] ?? 'employees.update';
    }

    private function approvalModel(string $type): ?string
    {
        $map = [
            'leave' => \App\Models\LeaveRequest::class,
            'overtime' => \App\Models\OvertimeRequest::class,
            'attendance' => \App\Models\AttendanceRequest::class,
            'budget' => \App\Models\BudgetRequest::class,
            'travel_report' => \App\Models\TravelReport::class,
        ];

        return $map[$type] ?? null;
    }

    /** Resolve karyawan by id/code/nama (scoped). Kembalikan Employee atau string error. */
    private function resolveEmployee(array $ref, ?int $companyId): Employee|string
    {
        $base = Employee::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        if (! empty($ref['employee_id'])) {
            $e = (clone $base)->find($ref['employee_id']);
        } elseif (! empty($ref['employee_code'])) {
            $e = (clone $base)->where('employee_code', $ref['employee_code'])->first();
        } elseif (! empty($ref['employee'])) {
            $matches = (clone $base)->where('full_name', 'like', '%'.$ref['employee'].'%')->limit(2)->get();
            if ($matches->count() > 1) {
                return "Nama '{$ref['employee']}' cocok ke >1 karyawan; gunakan employee_code/employee_id.";
            }
            $e = $matches->first();
        } else {
            return 'Wajib isi employee_id / employee_code / employee.';
        }

        return $e ?: 'Karyawan tidak ditemukan.';
    }

    /** Resolve shift dari {shift_id|shift} dalam satu perusahaan. Employee|string error. */
    private function resolveShift(array $ref, int $companyId): Shift|string
    {
        $base = Shift::query()->where('company_id', $companyId);

        if (! empty($ref['shift_id'])) {
            return (clone $base)->find($ref['shift_id']) ?: 'shift_id tidak ditemukan.';
        }
        if (! empty($ref['shift'])) {
            $exact = (clone $base)->whereRaw('LOWER(name) = ?', [mb_strtolower($ref['shift'])])->get();
            if ($exact->count() > 1) {
                return "shift '{$ref['shift']}' ganda (id: ".$exact->pluck('id')->implode(', ').'); gunakan shift_id.';
            }
            if ($exact->count() === 1) {
                return $exact->first();
            }

            return "shift '{$ref['shift']}' tidak ditemukan.";
        }

        return 'Wajib isi shift atau shift_id.';
    }

    private function validateShift(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'color' => 'nullable|string|max:7',
            'is_off' => 'nullable|boolean',
            'is_overnight' => 'nullable|boolean',
            'work_hours' => 'nullable|integer|min:1|max:24',
            'auto_overtime' => 'nullable|boolean',
        ]);
    }

    /** Buat sub-request yang "login" sebagai $actor untuk dipakai ulang store/approve. */
    private function actingAs(Employee $actor, array $input): Request
    {
        $sub = Request::create('/', 'POST', $input);
        $sub->setUserResolver(fn () => $actor);

        return $sub;
    }

    private function notifySuperadmins(?int $companyId, string $title, string $message): void
    {
        Employee::query()
            ->where('role', 'superadmin')
            ->where('is_active', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get()
            ->each(fn ($sa) => Notification::create([
                'employee_id' => $sa->id,
                'title' => $title,
                'message' => $message,
                'type' => 'approval',
            ]));
    }

    private function fail(string $message, int $code)
    {
        return response()->json(['success' => false, 'message' => $message], $code);
    }
}

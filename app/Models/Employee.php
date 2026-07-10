<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'employee_code', 'company_id', 'department_id', 'work_schedule_id',
        'schedule_template_id', 'manager_id', 'approver_id', 'full_name',
        'email', 'phone', 'password',
        'birth_place', 'birth_date', 'gender', 'marital_status', 'blood_type',
        'religion', 'nik', 'npwp_15', 'npwp_16', 'ptkp',
        'bpjs_tk', 'bpjs_kesehatan', 'bank_account', 'bank_name',
        'postal_code', 'ktp_address', 'residential_address',
        'position', 'job_level', 'employment_status', 'join_date', 'resign_date',
        'resign_reason', 'resign_notes', 'last_working_date',
        'contract_start_date', 'contract_end_date',
        'internship_institution', 'internship_supervisor', 'internship_field_supervisor', 'internship_notes',
        'photo', 'face_photo', 'signature', 'is_active', 'role', 'fcm_token',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function scheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leavePolicies(): BelongsToMany
    {
        return $this->belongsToMany(LeavePolicy::class, 'leave_policy_employees')->withTimestamps();
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function attendanceRequests(): HasMany
    {
        return $this->hasMany(AttendanceRequest::class);
    }

    public function dataChangeRequests(): HasMany
    {
        return $this->hasMany(DataChangeRequest::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function payrollRunDetails(): HasMany
    {
        return $this->hasMany(PayrollRunDetail::class);
    }

    public function magicLinks(): HasMany
    {
        return $this->hasMany(EmployeeMagicLink::class);
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(EmployeePermissionOverride::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'employee_roles')->withTimestamps();
    }

    public function adminActivityLogs(): HasMany
    {
        return $this->hasMany(AdminActivityLog::class);
    }

    public function payroll(): HasMany
    {
        return $this->hasMany(EmployeePayroll::class);
    }

    public function activePayroll()
    {
        return $this->hasOne(EmployeePayroll::class)->where('is_active', true)->latest('effective_date');
    }

    public function payrollComponents(): HasMany
    {
        return $this->hasMany(EmployeePayrollComponent::class);
    }

    public function getMasaKerjaAttribute(): string
    {
        if (! $this->join_date) {
            return '-';
        }
        $diff = $this->join_date->diff(now());

        return "{$diff->y} Tahun {$diff->m} Bulan {$diff->d} Hari";
    }

    /**
     * Apakah karyawan berstatus bekerja pada $date — yaitu di antara tanggal masuk dan
     * tanggal kerja terakhir (inklusif). Dipakai untuk menjaga agar jadwal template mingguan
     * (yang tidak punya masa berlaku) tidak berlaku surut ke tanggal sebelum ia bergabung
     * atau setelah ia keluar.
     *
     * Tanggal yang belum diisi dianggap tidak membatasi (mis. `join_date` kosong → tak ada
     * batas bawah), agar data lama tanpa tanggal tidak mendadak hilang dari rekap.
     *
     * CATATAN: sengaja TIDAK dipakai di App\Support\ScheduledWorkingDays — pembagi pro-rate
     * payroll harus tetap "hari kerja sebulan penuh"; menyusutkannya membuat karyawan yang
     * join di tengah bulan menerima gaji penuh.
     */
    public function isEmployedOn(Carbon|string $date): bool
    {
        $date = self::normalizeDate($date);

        if ($this->join_date && $date->lt($this->join_date->copy()->startOfDay())) {
            return false;
        }

        $exit = $this->last_working_date ?: $this->resign_date;

        return ! ($exit && $date->gt(Carbon::parse($exit)->startOfDay()));
    }

    /** Riwayat template jadwal, terbaru dulu. */
    public function scheduleTemplateHistory(): HasMany
    {
        return $this->hasMany(EmployeeScheduleTemplate::class)->orderByDesc('effective_from');
    }

    /**
     * Tabel riwayat mungkin belum ada (migrasi belum jalan, atau test yang membangun skema
     * minimal sendiri). Hasilnya di-cache di container — yang dibangun ulang tiap request dan
     * tiap test — sehingga tidak jadi query berulang, tapi juga tidak basi antar test.
     */
    public static function hasScheduleTemplateHistory(): bool
    {
        $key = 'employee.schedule_template_history.table_exists';

        if (! app()->bound($key)) {
            app()->instance($key, Schema::hasTable('employee_schedule_templates'));
        }

        return (bool) app($key);
    }

    /**
     * Relasi yang perlu di-eager-load untuk meresolusi template per tanggal.
     * Dipakai di query yang memproses banyak karyawan/tanggal agar tidak N+1.
     *
     * @return array<int,string>
     */
    public static function scheduleTemplateEagerLoads(): array
    {
        return self::hasScheduleTemplateHistory()
            ? ['scheduleTemplateHistory.template.days.shift', 'scheduleTemplate.days.shift']
            : ['scheduleTemplate.days.shift'];
    }

    /**
     * Template jadwal yang BERLAKU pada $date: baris riwayat dengan `effective_from` terbesar
     * yang <= $date. Tanpa ini, template yang terpasang sekarang berlaku surut ke masa lalu —
     * mis. karyawan yang pindah dari 6 hari kerja ke 5 hari kerja akan terlihat "kerja Sabtu"
     * di seluruh bulan sebelumnya.
     *
     * Karyawan yang belum punya baris riwayat jatuh kembali ke `schedule_template_id`, agar
     * data lama berperilaku persis seperti sebelumnya.
     */
    public function scheduleTemplateOn(Carbon|string $date): ?ScheduleTemplate
    {
        $date = self::normalizeDate($date);

        if (self::hasScheduleTemplateHistory()) {
            $this->loadMissing('scheduleTemplateHistory.template.days.shift');

            if ($this->scheduleTemplateHistory->isNotEmpty()) {
                // Baris berlaku dengan template_id NULL = "sejak tanggal ini tanpa template".
                return $this->scheduleTemplateHistory
                    ->first(fn (EmployeeScheduleTemplate $r) => $r->effective_from->copy()->startOfDay()->lte($date))
                    ?->template;
            }
        }

        $this->loadMissing('scheduleTemplate.days.shift');

        return $this->scheduleTemplate;
    }

    /**
     * Tetapkan template jadwal yang berlaku SEJAK $effectiveFrom, sekaligus menyegarkan
     * penunjuk `schedule_template_id` bila perubahan itu berlaku hari ini.
     *
     * $templateId null = melepas template sejak tanggal itu. Tanpa ini, melepas template hanya
     * mengosongkan penunjuk sementara riwayat lama tetap berlaku — penunjuk dan riwayat jadi
     * bertentangan.
     */
    public function applyScheduleTemplate(?int $templateId, Carbon|string $effectiveFrom): EmployeeScheduleTemplate
    {
        $effectiveFrom = self::normalizeDate($effectiveFrom);

        $this->seedScheduleTemplateBaseline();

        // Dicocokkan dengan whereDate, bukan updateOrCreate: sebagian driver menyimpan
        // `effective_from` sebagai "Y-m-d H:i:s" sehingga pencocokan string "Y-m-d" meleset
        // dan menghasilkan baris ganda pada tanggal yang sama.
        $row = EmployeeScheduleTemplate::where('employee_id', $this->id)
            ->whereDate('effective_from', $effectiveFrom->toDateString())
            ->first();

        if ($row) {
            $row->update(['template_id' => $templateId]);
        } else {
            $row = EmployeeScheduleTemplate::create([
                'employee_id' => $this->id,
                'template_id' => $templateId,
                'effective_from' => $effectiveFrom->toDateString(),
            ]);
        }

        $this->unsetRelation('scheduleTemplateHistory');
        $this->pruneRedundantScheduleTemplateRows();

        // Penunjuk selalu mencerminkan template yang berlaku HARI INI.
        $this->update(['schedule_template_id' => $this->scheduleTemplateOn(now())?->id]);

        return $row;
    }

    /**
     * Buang baris riwayat yang tidak mengubah apa pun: template-nya sama dengan yang sudah
     * berlaku sebelumnya. Tanpa ini, membolak-balik template atau mengoreksi tanggal yang
     * salah meninggalkan baris usang — riwayatnya tetap menghasilkan jadwal yang benar,
     * tapi jadi sulit dibaca dan tidak pernah kembali ke bentuk semula.
     */
    private function pruneRedundantScheduleTemplateRows(): void
    {
        if (! self::hasScheduleTemplateHistory()) {
            return;
        }

        $rows = EmployeeScheduleTemplate::where('employee_id', $this->id)
            ->orderBy('effective_from')
            ->get();

        $sebelumnya = null; // template yang berlaku sebelum baris ini; null = tak bertemplate
        $buang = [];

        foreach ($rows as $row) {
            if ($row->template_id === $sebelumnya) {
                $buang[] = $row->id;

                continue;
            }
            $sebelumnya = $row->template_id;
        }

        if ($buang !== []) {
            EmployeeScheduleTemplate::whereIn('id', $buang)->delete();
            $this->unsetRelation('scheduleTemplateHistory');
        }
    }

    /**
     * Pastikan karyawan yang SUDAH bertemplate punya baris riwayat dasar sebelum pergantian
     * pertamanya dicatat. Tanpa ini, mencatat "template baru sejak 18 Mei" membuat seluruh
     * tanggal SEBELUM 18 Mei kehilangan template — padahal dulu ia memang punya.
     *
     * Normalnya baris dasar dibuat oleh backfill migrasi. Ini jaring pengaman untuk karyawan
     * yang mendapat `schedule_template_id` lewat jalur lain (mis. dibuat setelah migrasi).
     */
    private function seedScheduleTemplateBaseline(): void
    {
        if (! self::hasScheduleTemplateHistory() || ! $this->schedule_template_id) {
            return;
        }

        $this->loadMissing('scheduleTemplateHistory');
        if ($this->scheduleTemplateHistory->isNotEmpty()) {
            return;
        }

        EmployeeScheduleTemplate::create([
            'employee_id' => $this->id,
            'template_id' => $this->schedule_template_id,
            'effective_from' => $this->join_date?->toDateString() ?? '1970-01-01',
        ]);

        $this->unsetRelation('scheduleTemplateHistory');
    }

    /**
     * Shift KERJA dari template mingguan untuk $date — sudah menghormati riwayat template DAN
     * masa kerja karyawan. Null bila belum masuk / sudah keluar / tak bertemplate / hari itu OFF.
     *
     * Override `schedule_assignments` dan hari libur TIDAK diperiksa di sini; pemanggil yang
     * menentukan urutannya (override menang atas libur, libur menang atas template).
     */
    public function templateShiftOn(Carbon|string $date): ?Shift
    {
        $date = self::normalizeDate($date);

        if (! $this->isEmployedOn($date)) {
            return null;
        }

        $shift = $this->scheduleTemplateOn($date)?->getShiftForDay($date->dayOfWeekIso);

        return $shift && ! $shift->is_off ? $shift : null;
    }

    private static function normalizeDate(Carbon|string $date): Carbon
    {
        return $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse($date)->startOfDay();
    }
}

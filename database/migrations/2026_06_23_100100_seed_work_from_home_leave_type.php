<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kuota WFH default per tahun. Ubah angka ini bila perlu. */
    private const WFH_DAYS_PER_YEAR = 24;

    public function up(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        $wfhType = LeaveType::firstOrCreate(
            ['name' => 'Work From Home'],
            ['max_days' => self::WFH_DAYS_PER_YEAR]
        );

        // Kebijakan per perusahaan agar saldo tahun berikutnya ikut ter-generate
        // otomatis oleh command leave:generate-annual.
        if (Schema::hasTable('leave_policies')) {
            foreach (Company::all() as $company) {
                LeavePolicy::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'leave_type_id' => $wfhType->id,
                    ],
                    [
                        'days_per_year' => self::WFH_DAYS_PER_YEAR,
                        'min_tenure_months' => 0,
                        'max_carry_over' => 0,
                        'is_prorated' => false,
                        'is_active' => true,
                    ]
                );
            }
        }

        // Saldo WFH tahun berjalan untuk semua karyawan aktif.
        if (Schema::hasTable('leave_balances')) {
            $year = now()->year;

            foreach (Employee::where('is_active', true)->get() as $employee) {
                LeaveBalance::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_type_id' => $wfhType->id,
                        'year' => $year,
                    ],
                    [
                        'total_days' => self::WFH_DAYS_PER_YEAR,
                        'carry_over' => 0,
                        'used_days' => 0,
                        'remaining_days' => self::WFH_DAYS_PER_YEAR,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        $wfhType = LeaveType::where('name', 'Work From Home')->first();
        if (! $wfhType) {
            return;
        }

        if (Schema::hasTable('leave_balances')) {
            LeaveBalance::where('leave_type_id', $wfhType->id)->delete();
        }
        if (Schema::hasTable('leave_policies')) {
            LeavePolicy::where('leave_type_id', $wfhType->id)->delete();
        }

        // LeaveType WFH hanya dihapus bila tidak ada pengajuan yang memakainya.
        $hasRequests = Schema::hasTable('leave_requests')
            && \App\Models\LeaveRequest::where('leave_type_id', $wfhType->id)->exists();

        if (! $hasRequests) {
            $wfhType->delete();
        }
    }
};

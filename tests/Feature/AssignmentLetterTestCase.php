<?php

namespace Tests\Feature;

use App\Models\AssignmentLetter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Dasar test surat tugas: skema sqlite minimal (admin panel, portal karyawan, API) dan data
 * karyawan contoh. Tabel surat tugas & kolom budget_requests.assignment_letter_id dibuat lewat
 * file migrasinya sendiri supaya migrasi ikut teruji.
 */
abstract class AssignmentLetterTestCase extends TestCase
{
    protected const ADMIN = 1;
    protected const PERMANENT = 2;
    protected const CONTRACT = 3;
    protected const INTERN = 4;
    protected const OUTSOURCING = 5;
    protected const DIRECTOR = 6;
    protected const OTHER_COMPANY = 7;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->createAssignmentLetterSchema();

        DB::table('companies')->insert([['id' => 1, 'name' => 'PT Arta Teknologi Comunindo'], ['id' => 2, 'name' => 'PT Lain']]);
        DB::table('departments')->insert([
            ['id' => 1, 'company_id' => 1, 'name' => 'Software'],
            ['id' => 2, 'company_id' => 1, 'name' => 'Hardware'],
            ['id' => 3, 'company_id' => 1, 'name' => 'HRD'],
        ]);

        $employee = fn (int $id, string $name, string $status, array $extra = []) => array_merge([
            'id' => $id, 'company_id' => 1, 'department_id' => 1, 'employee_code' => sprintf('%03d/SOFTW/I/2024', $id),
            'full_name' => $name, 'position' => 'Software Engineer', 'job_level' => 4,
            'employment_status' => $status, 'is_active' => true, 'role' => 'employee',
        ], $extra);

        DB::table('employees')->insert([
            $employee(self::ADMIN, 'Admin HRD', 'permanent', ['department_id' => 3, 'role' => 'hr_admin', 'position' => 'Hrd']),
            $employee(self::PERMANENT, 'Fadel Irsyad', 'permanent'),
            $employee(self::CONTRACT, 'Prastowo Dian', 'contract', ['department_id' => 2, 'position' => 'RND']),
            $employee(self::INTERN, 'Anak Magang', 'intern'),
            $employee(self::OUTSOURCING, 'Petugas Outsourcing', 'outsourcing'),
            $employee(self::DIRECTOR, 'Sofyan Ariyanto', 'permanent', ['position' => 'Director', 'job_level' => 1, 'department_id' => null]),
            $employee(self::OTHER_COMPANY, 'Karyawan PT Lain', 'permanent', ['company_id' => 2]),
        ]);
    }

    protected function payload(array $overrides = []): array
    {
        return array_replace([
            'letter_date' => '2026-01-05',
            'city' => 'Yogyakarta',
            'signer_id' => self::DIRECTOR,
            'signer_name' => 'Sofyan Ariyanto, S.T',
            'signer_position' => 'Direktur',
            'purpose' => '',
            'departure_date' => '2026-01-06',
            'work_start_date' => '2026-01-07',
            'return_date' => '2026-01-09',
            'initial_boxes' => '',
            'employees' => [
                ['employee_id' => self::PERMANENT, 'position' => 'Software Division'],
                ['employee_id' => self::CONTRACT, 'position' => ''],
            ],
            'destinations' => [
                ['activity' => 'Riset dan update firmware di Bendungan Ciawi', 'address' => 'Jl. Panatraco, Megamendung, Bogor, Jawa Barat', 'distance_km' => '590'],
            ],
            'facilities' => [
                ['type' => 'device', 'communication_device_id' => '', 'description' => '', 'inspection_result' => '', 'inspected_on' => ''],
                ['type' => 'transport', 'vehicle_id' => '', 'description' => 'Kereta Api', 'inspection_result' => '', 'inspected_on' => ''],
            ],
        ], $overrides);
    }

    protected function asRole(string $role, int $id = self::ADMIN): static
    {
        DB::table('employees')->where('id', $id)->update(['role' => $role]);
        // AdminPermission singleton menyimpan cache role; di test, container tidak dibangun
        // ulang antar request, jadi cache role sebelumnya harus dibuang.
        $this->app->forgetInstance(\App\Support\AdminPermission::class);

        return $this->withSession(['admin_id' => $id]);
    }

    protected function storeDraft(array $overrides = []): AssignmentLetter
    {
        $this->asRole('hr_admin')
            ->post(route('admin.assignment-letters.store'), $this->payload($overrides))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return AssignmentLetter::latest('id')->firstOrFail();
    }

    protected function issue(AssignmentLetter $letter): AssignmentLetter
    {
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $letter->id))->assertSessionHas('success');

        return $letter->fresh();
    }

    protected function createAssignmentLetterSchema(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('employee_code')->nullable();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('position')->nullable();
            $table->unsignedTinyInteger('job_level')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('employment_status')->default('contract');
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->string('photo')->nullable();
            $table->string('signature')->nullable();
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->boolean('is_late')->default(false);
            $table->boolean('is_remote')->default(false);
            $table->string('review_status')->nullable();
            $table->text('suspicious_reason')->nullable();
            $table->text('remote_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->timestamps();
        });

        foreach (['overtime_requests', 'attendance_requests'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->date('date');
                $table->string('overtime_type')->default('workday');
                $table->integer('total_duration')->default(0);
                $table->integer('break_duration')->default(0);
                $table->time('clock_in')->nullable();
                $table->time('clock_out')->nullable();
                $table->text('reason')->nullable();
                $table->string('status')->default('pending');
                $table->integer('current_step')->default(1);
                $table->timestamps();
            });
        }

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->date('date');
            $table->string('name')->nullable();
            $table->boolean('is_national')->default(false);
            $table->timestamps();
        });

        Schema::create('travel_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone');
            $table->string('name');
            $table->unsignedInteger('min_km');
            $table->unsignedInteger('max_km')->nullable();
            $table->decimal('meal_allowance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('request_type');
            $table->unsignedTinyInteger('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();
        });

        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('approver_id');
            $table->string('action');
            $table->unsignedTinyInteger('step_order')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('info');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->integer('file_size')->default(0);
            $table->timestamps();
        });

        Schema::create('budget_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('type')->default('budget');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('surat_tugas_no')->nullable();
            $table->date('surat_tugas_date')->nullable();
            $table->unsignedInteger('distance_km')->nullable();
            $table->unsignedBigInteger('travel_zone_id')->nullable();
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->unsignedTinyInteger('lhp_deadline_days')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->string('type');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('budget_request_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('editor_id')->nullable();
            $table->unsignedTinyInteger('step_order')->nullable();
            $table->json('before');
            $table->json('after');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_request_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();
        });

        Schema::create('budget_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('travel_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('budget_request_id')->nullable();
            $table->unsignedBigInteger('resubmission_of_id')->nullable();
            $table->string('surat_tugas_no')->nullable();
            $table->date('surat_tugas_date')->nullable();
            $table->string('destination_city')->default('-');
            $table->unsignedInteger('distance_km')->nullable();
            $table->unsignedBigInteger('travel_zone_id')->nullable();
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->date('submission_deadline')->nullable();
            $table->boolean('is_late')->default(false);
            $table->text('purpose')->nullable();
            $table->text('conclusion')->nullable();
            $table->json('recommendations')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->timestamps();
        });

        Schema::create('lpjs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('travel_report_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->timestamps();
        });

        (require database_path('migrations/2026_09_30_100000_create_assignment_letter_tables.php'))->up();
        (require database_path('migrations/2026_09_30_110000_add_assignment_letter_id_to_budget_requests.php'))->up();
        (require database_path('migrations/2026_10_01_100000_add_signed_file_to_assignment_letters.php'))->up();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TessaSelfServiceCapabilitiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['tokenable_type', 'tokenable_id']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_days')->default(12);
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->integer('year');
            $table->decimal('total_days', 5, 1)->default(0);
            $table->decimal('carry_over', 5, 1)->default(0);
            $table->decimal('used_days', 5, 1)->default(0);
            $table->decimal('remaining_days', 5, 1)->default(0);
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_off')->default(false);
            $table->boolean('is_overnight')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id');
            $table->date('date');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('request_type');
            $table->integer('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();
        });

        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('approver_id');
            $table->unsignedBigInteger('acted_by_id')->nullable();
            $table->string('action');
            $table->integer('step_order')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('overtime_type')->default('workday');
            $table->time('planned_start')->nullable();
            $table->time('planned_end')->nullable();
            $table->integer('pre_shift_duration')->default(0);
            $table->integer('pre_shift_break')->default(0);
            $table->integer('post_shift_duration')->default(0);
            $table->integer('post_shift_break')->default(0);
            $table->integer('break_duration')->default(0);
            $table->integer('total_duration')->default(0);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->timestamps();
        });
    }

    private function employee(string $email, ?string $phone = null, string $role = 'employee'): Employee
    {
        return Employee::create([
            'employee_code' => 'EMP-'.substr(md5($email), 0, 8),
            'company_id' => Company::firstOrCreate(['name' => 'PT Tessa'])->id,
            'full_name' => 'Emp '.$email,
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function token(Employee $employee): string
    {
        return $employee->createToken('tessa', ['tessa'])->plainTextToken;
    }

    private function scheduleSpreadsheet(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['employee_code', 'date', 'shift', 'notes'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($headers as $columnIndex => $header) {
                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 2], $row[$header] ?? null);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'tessa-schedule-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'jadwal-security.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_employee_can_check_own_leave_balances(): void
    {
        $employee = $this->employee('self@t.test');
        $other = $this->employee('other@t.test');

        DB::table('leave_types')->insert([
            ['id' => 1, 'name' => 'Cuti Tahunan', 'max_days' => 12, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'WFH', 'max_days' => 12, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('leave_balances')->insert([
            ['employee_id' => $employee->id, 'leave_type_id' => 1, 'year' => 2026, 'total_days' => 12, 'carry_over' => 1, 'used_days' => 4, 'remaining_days' => 9, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => $employee->id, 'leave_type_id' => 2, 'year' => 2026, 'total_days' => 6, 'carry_over' => 0, 'used_days' => 1, 'remaining_days' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => $other->id, 'leave_type_id' => 1, 'year' => 2026, 'total_days' => 12, 'carry_over' => 0, 'used_days' => 0, 'remaining_days' => 12, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->withToken($this->token($employee))->getJson('/api/tessa/leave-balances?year=2026');
        $response->assertOk();

        $this->assertSame(2, $response->json('count'));
        $this->assertSame(['Cuti Tahunan', 'WFH'], collect($response->json('data'))->pluck('leave_type')->all());
        $this->assertEquals([9.0, 5.0], collect($response->json('data'))->pluck('remaining_days')->all());
    }

    public function test_employee_can_check_own_schedule_range(): void
    {
        $employee = $this->employee('self@t.test');
        $other = $this->employee('other@t.test');

        $pagi = DB::table('shifts')->insertGetId([
            'company_id' => $employee->company_id, 'name' => 'Pagi', 'start_time' => '08:00', 'end_time' => '17:00',
            'is_off' => false, 'is_overnight' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $malam = DB::table('shifts')->insertGetId([
            'company_id' => $employee->company_id, 'name' => 'Malam', 'start_time' => '20:00', 'end_time' => '05:00',
            'is_off' => false, 'is_overnight' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('schedule_assignments')->insert([
            ['employee_id' => $employee->id, 'shift_id' => $pagi, 'date' => '2026-07-06', 'notes' => 'Briefing', 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => $other->id, 'shift_id' => $malam, 'date' => '2026-07-06', 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->withToken($this->token($employee))->getJson('/api/tessa/schedules?from=2026-07-06&to=2026-07-06');
        $response->assertOk();

        $this->assertSame(1, $response->json('count'));
        $this->assertSame('Pagi', $response->json('data.0.shift.name'));
        $this->assertSame('Briefing', $response->json('data.0.notes'));
    }

    public function test_hr_admin_can_preview_schedule_import_from_excel(): void
    {
        $admin = $this->employee('hr@t.test', null, 'hr_admin');
        $security = $this->employee('security@t.test');
        $shiftId = DB::table('shifts')->insertGetId([
            'company_id' => $admin->company_id,
            'name' => 'Malam',
            'start_time' => '20:00',
            'end_time' => '05:00',
            'is_off' => false,
            'is_overnight' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token($admin))->post('/api/tessa/schedules/import', [
            'dry_run' => '1',
            'file' => $this->scheduleSpreadsheet([
                ['employee_code' => $security->employee_code, 'date' => '2026-07-06', 'shift' => 'Malam', 'notes' => 'Pos 1'],
            ]),
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('dry_run'));
        $this->assertSame(1, $response->json('parsed'));
        $this->assertSame(1, $response->json('valid'));
        $this->assertSame('would_create', $response->json('results.0.action'));
        $this->assertDatabaseMissing('schedule_assignments', [
            'employee_id' => $security->id,
            'shift_id' => $shiftId,
            'date' => '2026-07-06',
        ]);
    }

    public function test_hr_admin_can_save_schedule_import_from_excel(): void
    {
        $admin = $this->employee('hr@t.test', null, 'hr_admin');
        $security = $this->employee('security@t.test');
        $shiftId = DB::table('shifts')->insertGetId([
            'company_id' => $admin->company_id,
            'name' => 'Malam',
            'start_time' => '20:00',
            'end_time' => '05:00',
            'is_off' => false,
            'is_overnight' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token($admin))->post('/api/tessa/schedules/import', [
            'file' => $this->scheduleSpreadsheet([
                ['employee_code' => $security->employee_code, 'date' => '2026-07-06', 'shift' => 'Malam', 'notes' => 'Pos 1'],
            ]),
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('dry_run'));
        $this->assertSame(1, $response->json('parsed'));
        $this->assertSame(1, $response->json('valid'));
        $this->assertDatabaseHas('schedule_assignments', [
            'employee_id' => $security->id,
            'shift_id' => $shiftId,
            'date' => '2026-07-06 00:00:00',
            'notes' => 'Pos 1',
        ]);
    }

    public function test_schedule_import_pdf_returns_clear_error_until_pdf_parser_is_available(): void
    {
        $admin = $this->employee('hr@t.test', null, 'hr_admin');

        $response = $this->withToken($this->token($admin))->post('/api/tessa/schedules/import', [
            'file' => UploadedFile::fake()->create('jadwal-security.pdf', 12, 'application/pdf'),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('PDF belum bisa diparse otomatis', $response->json('message'));
    }

    public function test_employee_can_check_next_approver_for_own_request(): void
    {
        $employee = $this->employee('self@t.test');
        $approver = $this->employee('lead@t.test', '081211111');

        DB::table('employee_approvers')->insert([
            'employee_id' => $employee->id,
            'request_type' => 'overtime',
            'step_order' => 2,
            'approver_id' => $approver->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('overtime_requests')->insert([
            'id' => 10,
            'employee_id' => $employee->id,
            'date' => '2026-07-06',
            'reason' => 'Closing',
            'status' => 'in_review',
            'current_step' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token($employee))->getJson('/api/tessa/approvals/overtime/10/next');
        $response->assertOk();

        $this->assertFalse($response->json('is_final'));
        $this->assertSame(2, $response->json('current_step'));
        $this->assertSame($approver->id, $response->json('approver.id'));
        $this->assertSame('081211111', $response->json('approver.phone'));
    }

    public function test_employee_can_edit_own_pending_overtime_request(): void
    {
        $employee = $this->employee('self@t.test');
        DB::table('overtime_requests')->insert([
            'id' => 10,
            'employee_id' => $employee->id,
            'date' => '2026-07-06',
            'reason' => 'Sebelum revisi',
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token($employee))->putJson('/api/tessa/requests/overtime/10', [
            'date' => '2026-07-07',
            'overtime_type' => 'workday',
            'post_shift_duration' => 120,
            'post_shift_break' => 15,
            'reason' => 'Revisi lembur',
        ]);
        $response->assertOk();

        $this->assertDatabaseHas('overtime_requests', [
            'id' => 10,
            'date' => '2026-07-07 00:00:00',
            'total_duration' => 120,
            'break_duration' => 15,
            'reason' => 'Revisi lembur (via Tessa)',
        ]);
    }

    public function test_employee_cannot_edit_processed_overtime_request(): void
    {
        $employee = $this->employee('self@t.test');
        DB::table('overtime_requests')->insert([
            'id' => 10,
            'employee_id' => $employee->id,
            'date' => '2026-07-06',
            'reason' => 'Final',
            'status' => 'approved',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($this->token($employee))->putJson('/api/tessa/requests/overtime/10', [
            'date' => '2026-07-07',
            'reason' => 'Tidak boleh',
        ])->assertStatus(422);

        $this->assertDatabaseHas('overtime_requests', ['id' => 10, 'reason' => 'Final']);
    }
}

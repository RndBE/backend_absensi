<?php

namespace Tests\Feature;

use App\Mail\EmployeePortalMagicLinkMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeePortalMagicLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Carbon::setTestNow('2026-06-17 10:00:00');

        foreach ([
            'employee_magic_links',
            'attendance_requests',
            'overtime_requests',
            'leave_requests',
            'departments',
            'employees',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('work_schedule_id')->nullable();
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('role')->default('employee');
            $table->string('position')->nullable();
            $table->string('employment_status')->default('permanent');
            $table->date('join_date')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        foreach (['leave_requests', 'overtime_requests', 'attendance_requests'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }

        Schema::create('employee_magic_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('token_hash', 64)->unique();
            $table->string('redirect_path')->default('/employee/dashboard');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        DB::table('departments')->insert([
            'id' => 1,
            'company_id' => 1,
            'name' => 'Software',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            [
                'id' => 1,
                'company_id' => 1,
                'department_id' => 1,
                'employee_code' => 'ADM001',
                'full_name' => 'Admin HR',
                'email' => 'admin@example.test',
                'role' => 'superadmin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'company_id' => 1,
                'department_id' => 1,
                'employee_code' => 'EMP001',
                'full_name' => 'Employee One',
                'email' => 'employee@example.test',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'company_id' => 1,
                'department_id' => 1,
                'employee_code' => 'EMP002',
                'full_name' => 'Employee Two',
                'email' => 'employee-two@example.test',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'company_id' => 1,
                'department_id' => 1,
                'employee_code' => 'EMP003',
                'full_name' => 'Employee No Email',
                'email' => null,
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'company_id' => 1,
                'department_id' => 1,
                'employee_code' => 'EMP004',
                'full_name' => 'Inactive Employee',
                'email' => 'inactive@example.test',
                'role' => 'employee',
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_send_employee_portal_magic_link_email(): void
    {
        Mail::fake();

        $response = $this->withSession(['admin_id' => 1])
            ->post(route('admin.employees.portal-link.send', 2));

        $response->assertRedirect(route('admin.employees.index'));
        $response->assertSessionHas('success', 'Link portal employee berhasil dikirim ke employee@example.test.');

        Mail::assertSent(EmployeePortalMagicLinkMail::class, function (EmployeePortalMagicLinkMail $mail) {
            $this->assertSame('Employee One', $mail->employee->full_name);
            $this->assertStringContainsString('/employee/magic-login?token=', $mail->magicUrl);
            $this->assertTrue($mail->expiresAt->equalTo(now()->addMinutes(30)));

            parse_str(parse_url($mail->magicUrl, PHP_URL_QUERY), $query);
            $this->assertArrayHasKey('token', $query);
            $this->assertDatabaseHas('employee_magic_links', [
                'employee_id' => 2,
                'token_hash' => hash('sha256', $query['token']),
                'redirect_path' => '/employee/dashboard',
                'used_at' => null,
            ]);

            return true;
        });
    }

    public function test_admin_employee_index_shows_send_portal_link_action(): void
    {
        $this->withSession(['admin_id' => 1])
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('Kirim Link Portal')
            ->assertSee(route('admin.employees.portal-link.send', 2), false)
            ->assertSee('Kirim Link Portal ke Semua')
            ->assertSee(route('admin.employees.portal-link.send-all'), false);
    }

    public function test_admin_can_send_employee_portal_magic_link_to_all_active_employees_with_email(): void
    {
        Mail::fake();

        $response = $this->withSession(['admin_id' => 1])
            ->post(route('admin.employees.portal-link.send-all'));

        $response->assertRedirect(route('admin.employees.index'));
        $response->assertSessionHas('success', 'Link portal employee berhasil dikirim ke 2 karyawan.');

        Mail::assertSent(EmployeePortalMagicLinkMail::class, 2);
        Mail::assertSent(EmployeePortalMagicLinkMail::class, fn (EmployeePortalMagicLinkMail $mail) => $mail->employee->id === 2);
        Mail::assertSent(EmployeePortalMagicLinkMail::class, fn (EmployeePortalMagicLinkMail $mail) => $mail->employee->id === 3);
        Mail::assertNotSent(EmployeePortalMagicLinkMail::class, fn (EmployeePortalMagicLinkMail $mail) => in_array($mail->employee->id, [1, 4, 5], true));

        $this->assertDatabaseHas('employee_magic_links', ['employee_id' => 2, 'used_at' => null]);
        $this->assertDatabaseHas('employee_magic_links', ['employee_id' => 3, 'used_at' => null]);
        $this->assertDatabaseMissing('employee_magic_links', ['employee_id' => 1]);
        $this->assertDatabaseMissing('employee_magic_links', ['employee_id' => 4]);
        $this->assertDatabaseMissing('employee_magic_links', ['employee_id' => 5]);
    }

    public function test_magic_link_logs_employee_in_once_and_redirects_to_dashboard(): void
    {
        $plainToken = 'valid-token-for-employee-one';
        DB::table('employee_magic_links')->insert([
            'employee_id' => 2,
            'token_hash' => hash('sha256', $plainToken),
            'redirect_path' => '/employee/dashboard',
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/employee/magic-login?token='.$plainToken)
            ->assertRedirect(route('employee.dashboard'));

        $this->assertSame(2, session('employee_id'));
        $this->assertDatabaseMissing('employee_magic_links', [
            'employee_id' => 2,
            'used_at' => null,
        ]);

        session()->forget('employee_id');

        $this->get('/employee/magic-login?token='.$plainToken)
            ->assertRedirect(route('employee.login'))
            ->assertSessionHas('error', 'Link portal sudah digunakan atau sudah kedaluwarsa.');
    }

    public function test_expired_magic_link_redirects_to_employee_login(): void
    {
        $plainToken = 'expired-token';
        DB::table('employee_magic_links')->insert([
            'employee_id' => 2,
            'token_hash' => hash('sha256', $plainToken),
            'redirect_path' => '/employee/dashboard',
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $this->get('/employee/magic-login?token='.$plainToken)
            ->assertRedirect(route('employee.login'))
            ->assertSessionHas('error', 'Link portal sudah digunakan atau sudah kedaluwarsa.');

        $this->assertNull(session('employee_id'));
    }
}

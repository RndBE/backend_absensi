<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\LeaveRequestController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminLeaveDetailApprovalChainTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('employee_approvers');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('employees');

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('position')->nullable();
            $table->integer('job_level')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->unsignedBigInteger('delegate_to')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->nullable();
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
            $table->string('action');
            $table->integer('step_order')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_leave_detail_approval_chain_uses_configured_employee_approvers_not_legacy_approver_chain(): void
    {
        DB::table('departments')->insert([
            'id' => 1,
            'name' => 'Software',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            ['id' => 1, 'department_id' => 1, 'approver_id' => 2, 'full_name' => 'Shandy Bagus Ferdiansyah', 'email' => 'shandy@example.test', 'password' => 'secret', 'position' => 'Software Division', 'job_level' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'department_id' => 1, 'approver_id' => 3, 'full_name' => 'Fadel Muhammad Irsyad', 'email' => 'fadel@example.test', 'password' => 'secret', 'position' => 'Software Division', 'job_level' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'department_id' => 1, 'approver_id' => 4, 'full_name' => 'Nofiyanto', 'email' => 'nofiyanto@example.test', 'password' => 'secret', 'position' => 'Software Manager', 'job_level' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'department_id' => 1, 'approver_id' => null, 'full_name' => 'Sofyan Ariyanto', 'email' => 'sofyan@example.test', 'password' => 'secret', 'position' => 'Director', 'job_level' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('leave_types')->insert([
            'id' => 1,
            'name' => 'Cuti Sakit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_requests')->insert([
            'id' => 10,
            'employee_id' => 1,
            'leave_type_id' => 1,
            'start_date' => '2026-05-28',
            'end_date' => '2026-05-28',
            'total_days' => 1,
            'reason' => 'operasi gigi',
            'status' => 'approved',
            'current_step' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employee_approvers')->insert([
            ['employee_id' => 1, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 1, 'request_type' => 'leave', 'step_order' => 2, 'approver_id' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('approval_logs')->insert([
            ['approvable_type' => 'App\\Models\\LeaveRequest', 'approvable_id' => 10, 'approver_id' => 2, 'action' => 'approved', 'step_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['approvable_type' => 'App\\Models\\LeaveRequest', 'approvable_id' => 10, 'approver_id' => 3, 'action' => 'approved', 'step_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $view = app(LeaveRequestController::class)->show(10);
        $chainNames = collect($view->getData()['chain'])
            ->map(fn (array $step) => $step['employee']->full_name)
            ->values()
            ->all();

        $this->assertSame(['Fadel Muhammad Irsyad', 'Nofiyanto'], $chainNames);
        $this->assertNotContains('Sofyan Ariyanto', $chainNames);
    }

    public function test_leave_detail_view_uses_clean_responsive_approval_layout(): void
    {
        $view = file_get_contents(resource_path('views/admin/leaves/show.blade.php'));

        $this->assertStringContainsString('<div class="space-y-5">', $view);
        $this->assertStringContainsString('grid grid-cols-1 md:grid-cols-2', $view);
        $this->assertStringContainsString('id="approvalChainList"', $view);
        $this->assertStringContainsString('material-symbols-outlined', $view);
        $this->assertStringContainsString('aria-label="Selesai">&#10003;</div>', $view);
        $this->assertStringNotContainsString('>check_circle</span>', $view);
        $this->assertStringNotContainsString('max-w-3xl', $view);
        $this->assertStringNotContainsString('flex items-center gap-2 flex-wrap', $view);
        $this->assertStringNotContainsString('ðŸ', $view);
        $this->assertStringNotContainsString('â†', $view);
        $this->assertStringNotContainsString('Â·', $view);
    }
}

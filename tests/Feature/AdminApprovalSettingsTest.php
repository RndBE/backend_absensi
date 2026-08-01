<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminApprovalSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employee_approvers');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('request_type');
            $table->unsignedInteger('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();
            $table->unique(['employee_id', 'request_type', 'step_order']);
        });

        DB::table('employees')->insert([
            $this->employee(1, 1, 'Admin', true),
            $this->employee(2, 1, 'Finance User', true),
            $this->employee(3, 1, 'Lina', true),
            $this->employee(4, 1, 'Maritza', true),
            $this->employee(5, 2, 'Other Company', true),
            $this->employee(6, 1, 'Inactive Employee', false),
        ]);

        foreach (['budget', 'travel_report', 'lpj'] as $type) {
            DB::table('employee_approvers')->insert([
                'employee_id' => 2,
                'request_type' => $type,
                'step_order' => 1,
                'approver_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_employee_approval_replaces_lina_with_maritza_despite_blank_steps(): void
    {
        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->post(route('admin.employees.approvers.store', 2), [
                'chains' => [
                    'budget' => ['', 4, null],
                    'travel_report' => [4, ''],
                    'lpj' => [null, 4],
                ],
            ]);

        $response->assertRedirect(route('admin.employees.edit', 2));
        $response->assertSessionHasNoErrors();

        foreach (['budget', 'travel_report', 'lpj'] as $type) {
            $this->assertDatabaseMissing('employee_approvers', [
                'employee_id' => 2,
                'request_type' => $type,
                'approver_id' => 3,
            ]);
            $this->assertDatabaseHas('employee_approvers', [
                'employee_id' => 2,
                'request_type' => $type,
                'step_order' => 1,
                'approver_id' => 4,
            ]);
        }
    }

    public function test_employee_approval_rejects_cross_company_approver(): void
    {
        $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->from(route('admin.employees.edit', 2))
            ->post(route('admin.employees.approvers.store', 2), [
                'chains' => ['budget' => [5]],
            ])
            ->assertRedirect(route('admin.employees.edit', 2))
            ->assertSessionHasErrors('chains.budget.0');
    }

    public function test_employee_approval_rejects_inactive_approver(): void
    {
        $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->from(route('admin.employees.edit', 2))
            ->post(route('admin.employees.approvers.store', 2), [
                'chains' => ['budget' => [6]],
            ])
            ->assertRedirect(route('admin.employees.edit', 2))
            ->assertSessionHasErrors('chains.budget.0');
    }

    public function test_bulk_assign_normalizes_blank_steps_and_assigns_maritza(): void
    {
        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->post(route('admin.approval-rules.bulk-assign'), [
                'employee_ids' => [2],
                'apply_types' => ['budget', 'travel_report', 'lpj'],
                'approver_ids' => ['', 4, null],
            ]);

        $response->assertRedirect(route('admin.approval-rules.index', ['type' => 'budget']));
        $response->assertSessionHasNoErrors();

        foreach (['budget', 'travel_report', 'lpj'] as $type) {
            $this->assertDatabaseHas('employee_approvers', [
                'employee_id' => 2,
                'request_type' => $type,
                'step_order' => 1,
                'approver_id' => 4,
            ]);
        }
    }

    public function test_bulk_assign_rejects_chain_containing_only_blank_steps(): void
    {
        $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->from(route('admin.approval-rules.index'))
            ->post(route('admin.approval-rules.bulk-assign'), [
                'employee_ids' => [2],
                'apply_types' => ['budget'],
                'approver_ids' => ['', null],
            ])
            ->assertRedirect(route('admin.approval-rules.index'))
            ->assertSessionHasErrors('approver_ids')
            ->assertSessionDoesntHaveErrors('approver_ids.0');
    }

    public function test_bulk_assign_rejects_cross_company_requester(): void
    {
        $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->from(route('admin.approval-rules.index'))
            ->post(route('admin.approval-rules.bulk-assign'), [
                'employee_ids' => [5],
                'apply_types' => ['budget'],
                'approver_ids' => [4],
            ])
            ->assertRedirect(route('admin.approval-rules.index'))
            ->assertSessionHasErrors('employee_ids.0');
    }

    private function employee(int $id, int $companyId, string $name, bool $isActive): array
    {
        return [
            'id' => $id,
            'company_id' => $companyId,
            'full_name' => $name,
            'email' => "employee{$id}@example.test",
            'password' => 'password',
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

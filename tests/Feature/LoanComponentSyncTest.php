<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoanComponentSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'employee_payroll_components',
            'payroll_components',
            'loan_requests',
            'employees',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('employee_code')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('loan_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('amount', 15, 2);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('total_repayable', 15, 2)->default(0);
            $table->unsignedSmallInteger('installment_count');
            $table->decimal('monthly_installment', 15, 2);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->string('start_period', 7)->nullable();
            $table->text('purpose')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('category')->default('recurring');
            $table->decimal('default_amount', 15, 2)->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_auto')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_payroll_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('payroll_component_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->seedFixtures();
    }

    public function test_creating_active_loan_syncs_to_pinjaman_payroll_component(): void
    {
        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->post(route('admin.loan-requests.store'), [
                'employee_id' => 2,
                'amount' => 2000000,
                'interest_rate' => 0,
                'installment_count' => 4,
                'monthly_installment' => 500000,
                'start_period' => '2026-06',
                'status' => 'active',
                'purpose' => 'Pinjaman import manual',
            ]);

        $response->assertRedirect(route('admin.loan-requests.index'));

        $this->assertDatabaseHas('employee_payroll_components', [
            'employee_id' => 2,
            'payroll_component_id' => 10,
            'amount' => 500000,
            'is_active' => true,
        ]);
        $this->assertStringStartsWith(
            '2026-06-01',
            (string) DB::table('employee_payroll_components')->where('employee_id', 2)->where('payroll_component_id', 10)->value('start_date')
        );
    }

    public function test_paid_loan_deactivates_pinjaman_payroll_component_assignment(): void
    {
        DB::table('loan_requests')->insert([
            'id' => 1,
            'employee_id' => 2,
            'amount' => 2000000,
            'interest_rate' => 0,
            'interest_amount' => 0,
            'total_repayable' => 2000000,
            'installment_count' => 4,
            'monthly_installment' => 500000,
            'remaining_amount' => 1500000,
            'start_period' => '2026-06',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employee_payroll_components')->insert([
            'employee_id' => 2,
            'payroll_component_id' => 10,
            'amount' => 500000,
            'start_date' => '2026-06-01',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->put(route('admin.loan-requests.update', 1), [
                'employee_id' => 2,
                'amount' => 2000000,
                'interest_rate' => 0,
                'installment_count' => 4,
                'monthly_installment' => 500000,
                'remaining_amount' => 0,
                'start_period' => '2026-06',
                'status' => 'paid',
            ]);

        $response->assertRedirect(route('admin.loan-requests.show', 1));

        $this->assertDatabaseHas('employee_payroll_components', [
            'employee_id' => 2,
            'payroll_component_id' => 10,
            'is_active' => false,
        ]);
    }

    private function seedFixtures(): void
    {
        DB::table('employees')->insert([
            [
                'id' => 1,
                'company_id' => 1,
                'employee_code' => 'ADM001',
                'email' => 'admin@example.test',
                'password' => 'secret',
                'full_name' => 'Admin Payroll',
                'role' => 'payroll_admin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'company_id' => 1,
                'employee_code' => 'EMP001',
                'email' => 'employee@example.test',
                'password' => 'secret',
                'full_name' => 'Employee Loan',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('payroll_components')->insert([
            'id' => 10,
            'name' => 'Pinjaman',
            'type' => 'deduction',
            'category' => 'recurring',
            'default_amount' => 0,
            'is_taxable' => false,
            'is_auto' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

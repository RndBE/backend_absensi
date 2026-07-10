<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Employee::isEmployedOn() — penjaga masa berlaku jadwal. Template mingguan
 * (`schedule_template_id`) tidak punya tanggal berlaku, sehingga tanpa penjaga ini ia
 * berlaku surut ke tanggal sebelum karyawan bergabung (dan setelah ia keluar).
 */
class EmployeeIsEmployedOnTest extends TestCase
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
            $table->date('join_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->date('last_working_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->timestamps();
        });
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'employee_code' => 'EMP-'.uniqid(),
            'company_id' => Company::firstOrCreate(['name' => 'PT Tessa'])->id,
            'full_name' => 'Emp',
            'email' => uniqid().'@t.test',
            'is_active' => true,
            'role' => 'employee',
        ], $attributes));
    }

    public function test_days_before_join_date_are_not_employed(): void
    {
        $e = $this->employee(['join_date' => '2026-04-14']);

        $this->assertFalse($e->isEmployedOn(Carbon::parse('2026-04-13')));
        $this->assertTrue($e->isEmployedOn(Carbon::parse('2026-04-14'))); // hari pertama termasuk
        $this->assertTrue($e->isEmployedOn(Carbon::parse('2026-04-15')));
    }

    public function test_days_after_last_working_date_are_not_employed(): void
    {
        $e = $this->employee(['join_date' => '2024-01-01', 'last_working_date' => '2026-06-30']);

        $this->assertTrue($e->isEmployedOn(Carbon::parse('2026-06-30'))); // hari terakhir termasuk
        $this->assertFalse($e->isEmployedOn(Carbon::parse('2026-07-01')));
    }

    public function test_last_working_date_wins_over_resign_date(): void
    {
        $e = $this->employee([
            'join_date' => '2024-01-01',
            'last_working_date' => '2026-06-30',
            'resign_date' => '2026-06-15',
        ]);

        $this->assertTrue($e->isEmployedOn(Carbon::parse('2026-06-20')));
    }

    public function test_resign_date_used_when_last_working_date_missing(): void
    {
        $e = $this->employee(['join_date' => '2024-01-01', 'resign_date' => '2026-06-15']);

        $this->assertTrue($e->isEmployedOn(Carbon::parse('2026-06-15')));
        $this->assertFalse($e->isEmployedOn(Carbon::parse('2026-06-16')));
    }

    /** Data lama tanpa tanggal tidak boleh mendadak hilang dari rekap. */
    public function test_missing_dates_do_not_restrict(): void
    {
        $e = $this->employee();

        $this->assertTrue($e->isEmployedOn(Carbon::parse('2020-01-01')));
        $this->assertTrue($e->isEmployedOn(Carbon::parse('2030-01-01')));
    }

    public function test_accepts_string_date(): void
    {
        $e = $this->employee(['join_date' => '2026-04-14']);

        $this->assertFalse($e->isEmployedOn('2026-04-13'));
        $this->assertTrue($e->isEmployedOn('2026-04-14'));
    }
}

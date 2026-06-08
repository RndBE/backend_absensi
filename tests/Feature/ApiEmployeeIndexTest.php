<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\EmployeeController;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiEmployeeIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('photo')->nullable();
            $table->string('position')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_employee_index_returns_all_active_company_employees_without_pagination(): void
    {
        DB::table('departments')->insert([
            'id' => 1,
            'company_id' => 1,
            'name' => 'Operasional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($i = 1; $i <= 25; $i++) {
            DB::table('employees')->insert([
                'id' => $i,
                'company_id' => 1,
                'department_id' => 1,
                'employee_code' => sprintf('EMP%03d', $i),
                'full_name' => sprintf('Employee %03d', $i),
                'email' => sprintf('employee%03d@example.test', $i),
                'password' => 'password',
                'phone' => null,
                'photo' => null,
                'position' => 'Staff',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('employees')->insert([
            'id' => 26,
            'company_id' => 1,
            'department_id' => 1,
            'employee_code' => 'INACTIVE',
            'full_name' => 'Inactive Employee',
            'email' => 'inactive@example.test',
            'password' => 'password',
            'position' => 'Staff',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/api/employees', 'GET');
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new EmployeeController())->index($request);
        $body = $response->getData(true);

        $this->assertTrue($body['success']);
        $this->assertIsList($body['data']);
        $this->assertCount(25, $body['data']);
        $this->assertSame('Employee 001', $body['data'][0]['full_name']);
        $this->assertArrayNotHasKey('current_page', $body['data']);
    }
}

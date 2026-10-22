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
            $table->string('role')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function seedEmployee(int $id, string $name, ?string $role = null): void
    {
        DB::table('employees')->insert([
            'id' => $id,
            'company_id' => 1,
            'employee_code' => 'EMP'.$id,
            'full_name' => $name,
            'email' => "emp{$id}@example.test",
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function listNames(array $query = [], int $asId = 1): array
    {
        $request = Request::create('/api/employees', 'GET', $query);
        $request->setUserResolver(fn () => Employee::findOrFail($asId));

        return array_column((new EmployeeController())->index($request)->getData(true)['data'], 'full_name');
    }

    public function test_search_hides_superadmin_and_apple_demo_account(): void
    {
        $this->seedEmployee(1, 'Budi Staff', 'employee');
        $this->seedEmployee(2, 'Admin Sistem', 'superadmin');
        $this->seedEmployee(3, 'Apple Demo Account', 'employee');
        $this->seedEmployee(4, 'Citra Tanpa Role');

        $this->assertSame(['Budi Staff', 'Citra Tanpa Role'], $this->listNames());
        // Pencarian langsung memakai nama akun tersembunyi pun tidak menampilkannya.
        $this->assertSame([], $this->listNames(['search' => 'Apple']));
        $this->assertSame([], $this->listNames(['search' => 'Admin Sistem']));
    }

    public function test_exclude_self_leaves_the_requester_out(): void
    {
        $this->seedEmployee(1, 'Budi Staff', 'employee');
        $this->seedEmployee(4, 'Citra Tanpa Role');

        $this->assertSame(['Budi Staff', 'Citra Tanpa Role'], $this->listNames());
        $this->assertSame(['Citra Tanpa Role'], $this->listNames(['exclude_self' => '1']));
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

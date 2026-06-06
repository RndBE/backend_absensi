<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HolidayImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('holidays');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->date('date');
            $table->string('name');
            $table->boolean('is_national')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'date']);
        });

        DB::table('employees')->insert([
            'id' => 1,
            'company_id' => 10,
            'full_name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_import_national_holidays_uses_official_2026_dates(): void
    {
        $this->withoutMiddleware();
        session(['admin_id' => 1]);

        $response = $this->from(route('admin.holidays.index'))->post(route('admin.holidays.import-national'), [
            'year' => 2026,
        ]);

        $response->assertRedirect(route('admin.holidays.index'));
        $response->assertSessionHas('success');

        $this->assertSame(17, DB::table('holidays')->where('company_id', 10)->count());
        $this->assertTrue($this->holidayExists('2026-01-16', 'Isra Mikraj Nabi Muhammad SAW'));
        $this->assertTrue($this->holidayExists('2026-02-17', 'Tahun Baru Imlek 2577 Kongzili'));
        $this->assertDatabaseMissing('holidays', [
            'company_id' => 10,
            'date' => '2026-02-12',
        ]);
    }

    public function test_import_unknown_year_fails_instead_of_guessing_dates(): void
    {
        $this->withoutMiddleware();
        session(['admin_id' => 1]);

        $response = $this->from(route('admin.holidays.index'))->post(route('admin.holidays.import-national'), [
            'year' => 2030,
        ]);

        $response->assertRedirect(route('admin.holidays.index'));
        $response->assertSessionHas('error');
        $this->assertSame(0, DB::table('holidays')->count());
    }

    private function holidayExists(string $date, string $name): bool
    {
        return DB::table('holidays')
            ->where('company_id', 10)
            ->where('date', 'like', "$date%")
            ->where('name', $name)
            ->exists();
    }
}

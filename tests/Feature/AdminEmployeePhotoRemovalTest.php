<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Foto profil diganti dan dihapus karyawan sendiri tanpa approval. HRD tetap bisa menghapus foto
 * yang tidak pantas lewat halaman ubah karyawan (kotak "Hapus foto").
 */
class AdminEmployeePhotoRemovalTest extends TestCase
{
    private const PHOTO = 'employees/photos/tidak-pantas.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
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
            $table->string('employment_status')->nullable();
            $table->string('role')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('departments')->insert(['id' => 1, 'name' => 'Software', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employees')->insert([
            ['id' => 1, 'employee_code' => 'HRD01', 'full_name' => 'HRD', 'email' => 'hrd@example.test', 'password' => 'x', 'photo' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'employee_code' => 'EMP02', 'full_name' => 'Budi', 'email' => 'budi@example.test', 'password' => 'x', 'photo' => self::PHOTO, 'created_at' => now(), 'updated_at' => now()],
        ]);
        Storage::disk('public')->put(self::PHOTO, 'gambar');
    }

    /** Isian wajib form ubah karyawan; [$extra] menimpa atau menambah. */
    private function form(array $extra = []): array
    {
        return array_merge([
            'employee_code' => 'EMP02',
            'full_name' => 'Budi',
            'email' => 'budi@example.test',
            'department_id' => 1,
            'employment_status' => 'permanent',
            'role' => 'employee',
        ], $extra);
    }

    private function update(array $extra = [])
    {
        return $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->put(route('admin.employees.update', 2), $this->form($extra));
    }

    public function test_hrd_removes_the_photo_file_and_clears_the_column(): void
    {
        $this->update(['remove_photo' => 1])
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHasNoErrors();

        $this->assertNull(Employee::find(2)->photo);
        Storage::disk('public')->assertMissing(self::PHOTO);
    }

    public function test_saving_without_the_remove_box_keeps_the_photo(): void
    {
        $this->update(['full_name' => 'Budi Santoso'])->assertRedirect(route('admin.employees.index'));

        $this->assertSame('Budi Santoso', Employee::find(2)->full_name);
        $this->assertSame(self::PHOTO, Employee::find(2)->photo);
        Storage::disk('public')->assertExists(self::PHOTO);
    }
}

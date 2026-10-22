<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Foto profil dari app: tanpa approval, sama seperti portal employee. */
class ApiProfilePhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Schema::dropIfExists('employees');
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        DB::table('employees')->insert([
            'id' => 1, 'company_id' => 1, 'employee_code' => 'EMP001', 'full_name' => 'Budi',
            'email' => 'budi@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Sanctum::actingAs(Employee::findOrFail(1));
    }

    public function test_upload_stores_photo_and_replaces_the_old_one(): void
    {
        $first = $this->postJson('/api/profile/photo', ['photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk()
            ->assertJsonPath('has_photo', true);
        $oldPath = Employee::find(1)->photo;
        Storage::disk('public')->assertExists($oldPath);
        $this->assertStringContainsString('/storage/employees/photos/', $first->json('photo'));

        $this->postJson('/api/profile/photo', ['photo' => UploadedFile::fake()->image('b.png')])->assertOk();

        $newPath = Employee::find(1)->photo;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_rejects_non_image_and_oversize_files(): void
    {
        $this->postJson('/api/profile/photo', ['photo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo');
        $this->postJson('/api/profile/photo', ['photo' => UploadedFile::fake()->image('big.jpg')->size(3000)])
            ->assertStatus(422);
        $this->postJson('/api/profile/photo', [])->assertStatus(422);

        $this->assertNull(Employee::find(1)->photo);
    }

    public function test_delete_removes_file_and_clears_column(): void
    {
        $this->postJson('/api/profile/photo', ['photo' => UploadedFile::fake()->image('a.jpg')])->assertOk();
        $path = Employee::find(1)->photo;

        $this->deleteJson('/api/profile/photo')
            ->assertOk()
            ->assertJsonPath('has_photo', false)
            ->assertJsonPath('photo', null);

        $this->assertNull(Employee::find(1)->photo);
        Storage::disk('public')->assertMissing($path);
        // Menghapus saat sudah kosong tidak error.
        $this->deleteJson('/api/profile/photo')->assertOk();
    }
}

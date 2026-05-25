<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_request_rejects_unsupported_attachment_type(): void
    {
        Sanctum::actingAs($this->employee());

        $response = $this->post('/api/budget/requests', [
            'type' => 'budget',
            'title' => 'Test Budget',
            'items' => [
                [
                    'type' => 'transport',
                    'description' => 'Taxi',
                    'amount' => 50000,
                ],
            ],
            'attachments' => [
                UploadedFile::fake()->create('script.exe', 1, 'application/x-msdownload'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('attachments.0');
    }

    public function test_clock_in_rejects_invalid_base64_photo(): void
    {
        Sanctum::actingAs($this->employee());
        Setting::setValue('require_photo', '1');
        Setting::setValue('require_gps', '0');
        Setting::setValue('face_verification_enabled', '0');

        $response = $this->postJson('/api/attendance/clock-in', [
            'photo_base64' => 'not-a-real-image',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('photo_base64');
    }

    private function employee(): Employee
    {
        $company = Company::create(['name' => 'Test Company']);

        return Employee::create([
            'employee_code' => 'EMP-' . fake()->unique()->numerify('####'),
            'company_id' => $company->id,
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'employee',
        ]);
    }
}

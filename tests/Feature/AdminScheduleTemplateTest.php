<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateDay;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScheduleTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_template_form_does_not_contain_nested_delete_form(): void
    {
        [$admin, $template] = $this->templateFixture();

        $response = $this
            ->withSession(['admin_id' => $admin->id])
            ->get(route('admin.schedule-templates.index'));

        $response->assertOk();
        $html = $response->getContent();

        $updateFormStart = strpos($html, 'id="editTemplateForm-' . $template->id . '"');
        $updateFormEnd = strpos($html, '</form>', $updateFormStart);
        $updateFormHtml = substr($html, $updateFormStart, $updateFormEnd - $updateFormStart);

        $this->assertNotFalse($updateFormStart);
        $this->assertNotFalse($updateFormEnd);
        $this->assertStringContainsString('value="PUT"', $updateFormHtml);
        $this->assertStringNotContainsString('value="DELETE"', $updateFormHtml);
        $this->assertStringContainsString('form="editTemplateForm-' . $template->id . '"', $html);
    }

    private function templateFixture(): array
    {
        $company = Company::create(['name' => 'Test Company']);

        $admin = Employee::create([
            'employee_code' => 'ADM-001',
            'company_id' => $company->id,
            'full_name' => 'Admin User',
            'email' => 'admin-template@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'superadmin',
        ]);

        $shift = Shift::create([
            'company_id' => $company->id,
            'name' => 'Pagi',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'color' => '#3B82F6',
            'sort_order' => 1,
        ]);

        $template = ScheduleTemplate::create([
            'company_id' => $company->id,
            'name' => 'Template Test',
            'description' => 'Before edit',
        ]);

        foreach (range(1, 7) as $day) {
            ScheduleTemplateDay::create([
                'template_id' => $template->id,
                'day_of_week' => $day,
                'shift_id' => $shift->id,
            ]);
        }

        return [$admin, $template];
    }
}

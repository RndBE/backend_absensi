<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\BpjsSetting;
use App\Models\Employee;
use Database\Seeders\TaxBpjsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTaxSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bpjs_bulk_update_requires_bpjs_payload(): void
    {
        $admin = $this->admin();

        $response = $this
            ->withSession(['admin_id' => $admin->id])
            ->from(route('admin.tax.settings'))
            ->put(route('admin.tax.update-bpjs-all'), [
                'npp' => '12345678',
            ]);

        $response->assertRedirect(route('admin.tax.settings'));
        $response->assertSessionHasErrors('bpjs');
    }

    public function test_bpjs_bulk_update_saves_seeded_bpjs_settings(): void
    {
        $admin = $this->admin();
        $this->seed(TaxBpjsSeeder::class);

        $jht = BpjsSetting::where('key', 'jht_rate')->firstOrFail();

        $response = $this
            ->withSession(['admin_id' => $admin->id])
            ->from(route('admin.tax.settings'))
            ->put(route('admin.tax.update-bpjs-all'), [
                'npp' => '12345678',
                'bpjs' => [
                    'jht_rate' => [
                        'id' => $jht->id,
                        'company' => '4.00',
                        'employee' => '2.50',
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.tax.settings'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Semua tarif BPJS berhasil diupdate.');

        $jht->refresh();
        $this->assertEquals(4.0, $jht->value['company']);
        $this->assertEquals(2.5, $jht->value['employee']);
        $this->assertSame('12345678', $jht->npp);
    }

    private function admin(): Employee
    {
        $company = Company::create(['name' => 'Test Company']);

        return Employee::create([
            'employee_code' => 'ADM-001',
            'company_id' => $company->id,
            'full_name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'superadmin',
        ]);
    }
}

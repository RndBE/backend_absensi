<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoanRequestFlowTest extends TestCase
{
    public function test_backend_registers_manual_loan_crud_only(): void
    {
        $this->assertFileExists(app_path('Models/LoanRequest.php'));
        $this->assertFileExists(app_path('Http/Controllers/Admin/LoanRequestController.php'));
        $this->assertFileExists(resource_path('views/admin/loan-requests/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/loan-requests/show.blade.php'));
        $this->assertFileExists(resource_path('views/admin/loan-requests/create.blade.php'));
        $this->assertFileExists(resource_path('views/admin/loan-requests/edit.blade.php'));

        $apiRoutes = file_get_contents(base_path('routes/api.php'));
        $webRoutes = file_get_contents(base_path('routes/web.php'));
        $model = file_get_contents(app_path('Models/LoanRequest.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/LoanRequestController.php'));
        $index = file_get_contents(resource_path('views/admin/loan-requests/index.blade.php'));
        $form = file_get_contents(resource_path('views/admin/loan-requests/_form.blade.php'));
        $show = file_get_contents(resource_path('views/admin/loan-requests/show.blade.php'));

        $this->assertStringNotContainsString('/loan-requests', $apiRoutes);
        $this->assertStringNotContainsString('Api\LoanRequestController', $apiRoutes);
        $this->assertStringContainsString('/loan-requests', $webRoutes);
        $this->assertStringContainsString("name('loan-requests.create')", $webRoutes);
        $this->assertStringContainsString("name('loan-requests.store')", $webRoutes);
        $this->assertStringContainsString("name('loan-requests.edit')", $webRoutes);
        $this->assertStringContainsString("name('loan-requests.update')", $webRoutes);
        $this->assertStringContainsString("name('loan-requests.destroy')", $webRoutes);
        $this->assertStringContainsString('admin.loan-requests.index', file_get_contents(resource_path('views/admin/layouts/app.blade.php')));
        $this->assertStringContainsString('function create', $controller);
        $this->assertStringContainsString('function store', $controller);
        $this->assertStringContainsString('function edit', $controller);
        $this->assertStringContainsString('function update', $controller);
        $this->assertStringContainsString('function destroy', $controller);
        $this->assertStringContainsString("'interest_rate'", $model);
        $this->assertStringContainsString("'interest_amount'", $model);
        $this->assertStringContainsString("'total_repayable'", $model);
        $this->assertStringContainsString("'interest_rate' =>", $controller);
        $this->assertStringContainsString('total_repayable', $controller);
        $this->assertStringContainsString('name="interest_rate"', $form);
        $this->assertStringContainsString('Bunga', $index);
        $this->assertStringContainsString('Total Pinjaman', $show);
        $this->assertStringContainsString('Tambah Pinjaman', $index);
        $this->assertStringNotContainsString('Pengajuan Pinjaman', $index);
        $this->assertStringNotContainsString('Persetujuan Pinjaman', $index);
    }

    public function test_loan_form_supports_manual_and_automatic_monthly_installment_modes(): void
    {
        $form = file_get_contents(resource_path('views/admin/loan-requests/_form.blade.php'));

        $this->assertStringContainsString('data-loan-installment-form', $form);
        $this->assertStringContainsString('name="installment_mode"', $form);
        $this->assertStringContainsString('value="auto"', $form);
        $this->assertStringContainsString('value="manual"', $form);
        $this->assertStringContainsString('data-monthly-installment-input', $form);
        $this->assertStringContainsString('data-auto-installment-preview', $form);
        $this->assertStringContainsString('Dihitung otomatis saat disimpan', $form);
        $this->assertStringContainsString('addEventListener(\'change\'', $form);
    }

    public function test_approval_maps_do_not_include_loan_requests(): void
    {
        $approvalSources = [
            file_get_contents(app_path('Http/Controllers/Admin/ApprovalController.php')),
            file_get_contents(app_path('Http/Controllers/Api/ApprovalController.php')),
            file_get_contents(app_path('Http/Controllers/Admin/MonitorApprovalController.php')),
            file_get_contents(app_path('Http/Controllers/Admin/ApprovalRuleController.php')),
            file_get_contents(resource_path('views/admin/approvals/index.blade.php')),
            file_get_contents(resource_path('views/admin/employees/edit.blade.php')),
            file_get_contents(resource_path('views/admin/employees/show.blade.php')),
        ];

        foreach ($approvalSources as $source) {
            $this->assertStringNotContainsString('LoanRequest::class', $source);
            $this->assertStringNotContainsString("'loan'", $source);
            $this->assertStringNotContainsString('tab\' => \'loan', $source);
            $this->assertStringNotContainsString('type\' => \'loan', $source);
        }
    }
}

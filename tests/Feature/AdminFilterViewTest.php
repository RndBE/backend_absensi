<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminFilterViewTest extends TestCase
{
    public function test_leave_balance_uses_fuse_search_and_preserves_backend_filters_on_year_navigation(): void
    {
        $view = file_get_contents(resource_path('views/admin/leave-balances/index.blade.php'));

        $this->assertStringContainsString('fuse.js', $view);
        $this->assertStringContainsString('id="leaveBalanceSearch"', $view);
        $this->assertStringContainsString('data-fuse-row="leave-balance"', $view);
        $this->assertStringContainsString('data-search=', $view);
        $this->assertStringContainsString('threshold: 0.45', $view);
        $this->assertStringNotContainsString('name="search"', $view);
        $this->assertStringContainsString("'department_id' => \$departmentId", $view);
        $this->assertStringNotContainsString("'search' => \$search", $view);
        $this->assertStringContainsString("request()->filled('department_id')", $view);
    }

    public function test_attendance_recap_uses_fuse_search_and_preserves_backend_filters_on_date_navigation(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance-recap/index.blade.php'));

        $this->assertStringContainsString('fuse.js', $view);
        $this->assertStringContainsString('id="attendanceRecapSearch"', $view);
        $this->assertStringContainsString('data-fuse-row="attendance-recap"', $view);
        $this->assertStringContainsString('data-search=', $view);
        $this->assertStringNotContainsString('name="search"', $view);
        $this->assertStringContainsString("'department_id' => \$departmentId", $view);
        $this->assertStringContainsString("'status' => \$filterStatus", $view);
        $this->assertStringNotContainsString("'search' => \$search", $view);
        $this->assertStringContainsString("request()->filled('department_id') || request()->filled('status')", $view);
    }

    public function test_attendance_recap_stat_cards_use_status_color_accents(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance-recap/index.blade.php'));

        $this->assertStringContainsString('border-l-4', $view);
        $this->assertStringContainsString('border-l-emerald-500', $view);
        $this->assertStringContainsString('border-l-amber-500', $view);
        $this->assertStringContainsString('border-l-blue-500', $view);
        $this->assertStringContainsString('border-l-red-500', $view);
        $this->assertStringContainsString('border-l-slate-400', $view);
        $this->assertStringContainsString('border-l-rose-500', $view);
        $this->assertStringContainsString('bg-emerald-50', $view);
        $this->assertStringContainsString('bg-amber-50', $view);
        $this->assertStringContainsString('bg-blue-50', $view);
        $this->assertStringContainsString('bg-red-50', $view);
        $this->assertStringContainsString('bg-slate-50', $view);
        $this->assertStringContainsString('bg-rose-50', $view);
        $this->assertStringContainsString('w-10 h-10 rounded-lg ring-1 ring-inset flex items-center justify-center shrink-0', $view);
        $this->assertStringContainsString('material-symbols-outlined text-[22px] leading-none', $view);
        $this->assertStringNotContainsString('material-symbols-outlined text-[22px] w-10 h-10', $view);
    }

    public function test_employee_index_uses_fuse_search_and_preserves_backend_dropdown_filters(): void
    {
        $view = file_get_contents(resource_path('views/admin/employees/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/EmployeeController.php'));

        $this->assertStringContainsString('fuse.js', $view);
        $this->assertStringContainsString('id="employeeSearch"', $view);
        $this->assertStringContainsString('data-fuse-row="employee"', $view);
        $this->assertStringContainsString('data-search=', $view);
        $this->assertStringContainsString('threshold: 0.45', $view);
        $this->assertStringContainsString('<form method="GET" id="employeeFilterForm" class="flex items-center gap-3 mb-5 flex-wrap">', $view);
        $this->assertStringContainsString('onchange="document.getElementById(\'employeeFilterForm\').submit()"', $view);
        $this->assertSame(3, substr_count($view, 'w-full max-w-[280px]'));
        $this->assertSame(3, substr_count($view, 'h-[42px]'));
        $this->assertStringNotContainsString('type="submit"', $view);
        $this->assertStringNotContainsString('name="search"', $view);
        $this->assertStringNotContainsString('$request->search', $controller);
        $this->assertStringNotContainsString('paginate(15)', $controller);
    }

    public function test_payroll_component_index_uses_fuse_search_with_type_tabs(): void
    {
        $view = file_get_contents(resource_path('views/admin/payroll-components/index.blade.php'));

        $this->assertStringContainsString('fuse.js', $view);
        $this->assertStringContainsString('id="payrollComponentSearch"', $view);
        $this->assertStringContainsString('data-fuse-row="payroll-component"', $view);
        $this->assertStringContainsString('data-search=', $view);
        $this->assertStringContainsString('threshold: 0.45', $view);
        $this->assertStringContainsString("['type' => 'earning']", $view);
        $this->assertStringContainsString("['type' => 'deduction']", $view);
    }

    public function test_employee_payroll_index_auto_submits_department_filter_without_filter_button(): void
    {
        $view = file_get_contents(resource_path('views/admin/employee-payrolls/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/EmployeePayrollController.php'));

        $this->assertStringContainsString('fuse.js', $view);
        $this->assertStringContainsString('id="employeePayrollSearch"', $view);
        $this->assertStringContainsString('data-fuse-row="employee-payroll"', $view);
        $this->assertStringContainsString('data-search=', $view);
        $this->assertStringContainsString('threshold: 0.45', $view);
        $this->assertStringContainsString('<form method="GET" id="employeePayrollFilterForm" class="flex items-center gap-3 mb-5 flex-wrap">', $view);
        $this->assertStringContainsString('onchange="document.getElementById(\'employeePayrollFilterForm\').submit()"', $view);
        $this->assertSame(2, substr_count($view, 'w-full max-w-[280px]'));
        $this->assertSame(2, substr_count($view, 'h-[42px]'));
        $this->assertStringContainsString('class="inline-flex items-center px-4 py-2', $view);
        $this->assertStringNotContainsString('type="submit"', $view);
        $this->assertStringNotContainsString('name="search"', $view);
        $this->assertStringNotContainsString('>Filter</button>', $view);
        $this->assertStringContainsString("request()->filled('department_id')", $view);
        $this->assertStringNotContainsString('$request->search', $controller);
        $this->assertStringNotContainsString('paginate(20)', $controller);
    }

    public function test_payslip_index_uses_fuse_search_and_preserves_period_filter(): void
    {
        $view = file_get_contents(resource_path('views/admin/payslips/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/PayslipController.php'));

        $this->assertStringContainsString('fuse.js', $view);
        $this->assertStringContainsString('id="payslipSearch"', $view);
        $this->assertStringContainsString('data-fuse-row="payslip"', $view);
        $this->assertStringContainsString('data-search=', $view);
        $this->assertStringContainsString('threshold: 0.45', $view);
        $this->assertStringContainsString('<form method="GET" id="payslipFilterForm" class="flex items-center gap-3 mb-5 flex-wrap">', $view);
        $this->assertStringContainsString('onchange="document.getElementById(\'payslipFilterForm\').submit()"', $view);
        $this->assertSame(2, substr_count($view, 'w-full max-w-[280px]'));
        $this->assertSame(2, substr_count($view, 'h-[42px]'));
        $this->assertStringContainsString('class="inline-flex items-center px-4 py-2', $view);
        $this->assertStringNotContainsString('type="submit"', $view);
        $this->assertStringNotContainsString('name="search"', $view);
        $this->assertStringNotContainsString('$request->search', $controller);
        $this->assertStringNotContainsString('paginate(20)', $controller);
        $this->assertStringContainsString('request(\'period\')', $view);
    }

    public function test_policy_and_travel_zone_pages_use_standard_admin_card_layout(): void
    {
        foreach ([
            resource_path('views/admin/policies/index.blade.php'),
            resource_path('views/admin/travel-zones/index.blade.php'),
        ] as $path) {
            $view = file_get_contents($path);

            $this->assertStringContainsString('bg-white rounded-xl border border-gray-200 shadow-sm', $view);
            $this->assertStringContainsString('px-5 py-4 border-b border-gray-100 flex items-center justify-between', $view);
            $this->assertStringContainsString('<div class="p-5">', $view);
            $this->assertStringContainsString('<div class="overflow-x-auto">', $view);
            $this->assertStringNotContainsString('max-w-4xl mx-auto', $view);
        }
    }

    public function test_payroll_views_hide_manage_actions_without_manage_permissions(): void
    {
        $componentIndex = file_get_contents(resource_path('views/admin/payroll-components/index.blade.php'));
        $componentEmployees = file_get_contents(resource_path('views/admin/payroll-components/employees.blade.php'));
        $employeePayrollIndex = file_get_contents(resource_path('views/admin/employee-payrolls/index.blade.php'));
        $employeePayrollEdit = file_get_contents(resource_path('views/admin/employee-payrolls/edit.blade.php'));
        $payrollRunsIndex = file_get_contents(resource_path('views/admin/payroll-runs/index.blade.php'));

        foreach ([$componentIndex, $componentEmployees, $employeePayrollIndex, $employeePayrollEdit] as $view) {
            $this->assertStringContainsString("can(\$currentAdmin, 'payroll.master.manage')", $view);
        }

        $this->assertStringContainsString("can(\$currentAdmin, 'payroll.runs.create')", $payrollRunsIndex);
        $this->assertStringContainsString("can(\$currentAdmin, 'payroll.runs.delete')", $payrollRunsIndex);

        $permissions = config('admin_permissions.route_permissions');
        $this->assertSame('payroll.master.manage', $permissions['admin.employee-payrolls.edit']);
        $this->assertSame('payroll.master.manage', $permissions['admin.payroll-components.employees']);
    }

    public function test_admin_action_buttons_are_guarded_by_module_permissions(): void
    {
        $expectations = [
            'views/admin/employees/index.blade.php' => [
                "can(\$currentAdmin, 'employees.create')",
                "can(\$currentAdmin, 'employees.update')",
                "can(\$currentAdmin, 'employees.delete')",
            ],
            'views/admin/employees/show.blade.php' => [
                "can(\$currentAdmin, 'employees.update')",
                "can(\$currentAdmin, 'employees.delete')",
            ],
            'views/admin/attendance-recap/index.blade.php' => [
                "can(\$currentAdmin, 'attendance.manage')",
            ],
            'views/admin/leaves/index.blade.php' => [
                "can(\$currentAdmin, 'leaves.create')",
                "can(\$currentAdmin, 'leaves.delete')",
            ],
            'views/admin/approvals/index.blade.php' => [
                "can(\$currentAdmin, 'approvals.action')",
            ],
            'views/admin/budget-requests/index.blade.php' => [
                "can(\$currentAdmin, 'budget.manage')",
            ],
            'views/admin/budget-requests/show.blade.php' => [
                "can(\$currentAdmin, 'budget.manage')",
            ],
            'views/admin/travel-reports/index.blade.php' => [
                "can(\$currentAdmin, 'travel.reports.manage')",
            ],
            'views/admin/schedules/index.blade.php' => [
                "can(\$currentAdmin, 'schedule.manage')",
                "can(\$currentAdmin, 'schedule.master.manage')",
            ],
        ];

        foreach ($expectations as $viewPath => $needles) {
            $view = file_get_contents(resource_path($viewPath));

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $view, "{$viewPath} is missing {$needle}");
            }
        }
    }

    public function test_budget_request_detail_uses_wide_responsive_layout(): void
    {
        $view = file_get_contents(resource_path('views/admin/budget-requests/show.blade.php'));

        $this->assertStringNotContainsString('max-w-4xl mx-auto', $view);
        $this->assertStringContainsString('xl:grid-cols-[minmax(0,1fr)_380px]', $view);
        $this->assertStringContainsString('grid grid-cols-1 md:grid-cols-2 gap-3', $view);
        $this->assertStringContainsString('items.attachments', file_get_contents(app_path('Http/Controllers/Admin/BudgetRequestController.php')));
        $this->assertStringContainsString('Lampiran Item', $view);
        $this->assertStringNotContainsString('<table class="w-full mt-3">', $view);
        $this->assertStringNotContainsString('Â·', $view);
        $this->assertStringNotContainsString('â€”', $view);
    }
}

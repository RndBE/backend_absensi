<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\ApprovalRuleController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\LeaveRequestController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ScheduleTemplateController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\AttendanceRecapController;
use App\Http\Controllers\Admin\LeavePolicyController;
use App\Http\Controllers\Admin\LeaveBalanceController;
use App\Http\Controllers\Admin\AttendanceSettingController;
use App\Http\Controllers\Admin\CompanyController;
use Illuminate\Support\Facades\Route;

// Redirect root to admin
Route::get('/', fn() => redirect()->route('admin.login'));

// Admin Auth
Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

// Admin Protected
Route::prefix('admin')->name('admin.')->middleware(\App\Http\Middleware\AdminAuth::class)->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Employees
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{id}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('/employees/{id}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{id}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::get('/employees/{id}/resign', [EmployeeController::class, 'resign'])->name('employees.resign');
    Route::post('/employees/{id}/resign', [EmployeeController::class, 'processResign'])->name('employees.process-resign');

    // Employee Approver Chains
    Route::get('/employees/{id}/approvers', [\App\Http\Controllers\Admin\EmployeeApproverController::class, 'index'])->name('employees.approvers.index');
    Route::post('/employees/{id}/approvers', [\App\Http\Controllers\Admin\EmployeeApproverController::class, 'store'])->name('employees.approvers.store');

    // Departments
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::put('/departments/{id}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('/departments/{id}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

    // Attendance
    Route::get('/attendance/realtime', [AttendanceController::class, 'realtime'])->name('attendance.realtime');
    Route::get('/attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');

    // Leave Requests
    Route::get('/leaves', [LeaveRequestController::class, 'index'])->name('leaves.index');
    Route::get('/leaves/create', [LeaveRequestController::class, 'create'])->name('leaves.create');
    Route::post('/leaves', [LeaveRequestController::class, 'store'])->name('leaves.store');
    Route::get('/leaves/{id}', [LeaveRequestController::class, 'show'])->name('leaves.show');
    Route::delete('/leaves/{id}', [LeaveRequestController::class, 'destroy'])->name('leaves.destroy');

    // Leave Policies
    Route::get('/leave-policies', [LeavePolicyController::class, 'index'])->name('leave-policies.index');
    Route::post('/leave-policies', [LeavePolicyController::class, 'store'])->name('leave-policies.store');
    Route::put('/leave-policies/{leavePolicy}', [LeavePolicyController::class, 'update'])->name('leave-policies.update');
    Route::delete('/leave-policies/{leavePolicy}', [LeavePolicyController::class, 'destroy'])->name('leave-policies.destroy');

    // Leave Balances
    Route::get('/leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
    Route::post('/leave-balances/generate', [LeaveBalanceController::class, 'generate'])->name('leave-balances.generate');
    Route::put('/leave-balances/{leaveBalance}', [LeaveBalanceController::class, 'update'])->name('leave-balances.update');

    // Schedules
    Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
    Route::post('/schedules/bulk', [ScheduleController::class, 'bulkStore'])->name('schedules.bulk');
    Route::post('/schedules/clear', [ScheduleController::class, 'clearDay'])->name('schedules.clear');
    Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');

    // Attendance Recap
    Route::get('/attendance-recap', [AttendanceRecapController::class, 'index'])->name('attendance-recap.index');
    Route::post('/attendance-recap', [AttendanceRecapController::class, 'update'])->name('attendance-recap.update');
    Route::get('/attendance-recap/employee/{id}', [AttendanceRecapController::class, 'employeeDetail'])->name('attendance-recap.employee-detail');

    // Shifts (master)
    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::put('/shifts/{id}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::delete('/shifts/{id}', [ShiftController::class, 'destroy'])->name('shifts.destroy');

    // Schedule Templates
    Route::get('/schedule-templates', [ScheduleTemplateController::class, 'index'])->name('schedule-templates.index');
    Route::post('/schedule-templates', [ScheduleTemplateController::class, 'store'])->name('schedule-templates.store');
    Route::put('/schedule-templates/{id}', [ScheduleTemplateController::class, 'update'])->name('schedule-templates.update');
    Route::delete('/schedule-templates/{id}', [ScheduleTemplateController::class, 'destroy'])->name('schedule-templates.destroy');
    Route::post('/schedule-templates/assign', [ScheduleTemplateController::class, 'assignBulk'])->name('schedule-templates.assign');

    // Holidays
    Route::get('/holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::post('/holidays/import-national', [HolidayController::class, 'importNational'])->name('holidays.import-national');
    Route::delete('/holidays/{id}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    // Approvals
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/{type}/{id}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{type}/{id}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

    // Approval Rules (Recap Dashboard)
    Route::get('/approval-rules', [ApprovalRuleController::class, 'index'])->name('approval-rules.index');
    Route::post('/approval-rules/bulk-assign', [ApprovalRuleController::class, 'bulkAssign'])->name('approval-rules.bulk-assign');

    // Company Settings
    Route::get('/company', [CompanyController::class, 'index'])->name('company.index');
    Route::put('/company', [CompanyController::class, 'update'])->name('company.update');

    // Attendance Settings
    Route::get('/attendance-settings', [AttendanceSettingController::class, 'index'])->name('attendance-settings.index');
    Route::put('/attendance-settings', [AttendanceSettingController::class, 'update'])->name('attendance-settings.update');



    // Payroll Components
    Route::get('/payroll-components', [\App\Http\Controllers\Admin\PayrollComponentController::class, 'index'])->name('payroll-components.index');
    Route::post('/payroll-components', [\App\Http\Controllers\Admin\PayrollComponentController::class, 'store'])->name('payroll-components.store');
    Route::put('/payroll-components/{id}', [\App\Http\Controllers\Admin\PayrollComponentController::class, 'update'])->name('payroll-components.update');
    Route::post('/payroll-components/{id}/toggle', [\App\Http\Controllers\Admin\PayrollComponentController::class, 'toggle'])->name('payroll-components.toggle');
    Route::delete('/payroll-components/{id}', [\App\Http\Controllers\Admin\PayrollComponentController::class, 'destroy'])->name('payroll-components.destroy');
    // Assign employees to a component
    Route::get('/payroll-components/{id}/employees', [\App\Http\Controllers\Admin\PayrollComponentController::class, 'employees'])->name('payroll-components.employees');
    Route::post('/payroll-components/{id}/employees', [\App\Http\Controllers\Admin\PayrollComponentController::class, 'assignEmployee'])->name('payroll-components.assign-employee');
    Route::put('/payroll-components/{id}/employees/{assignId}', [\App\Http\Controllers\Admin\PayrollComponentController::class, 'updateAssignment'])->name('payroll-components.update-assignment');
    Route::delete('/payroll-components/{id}/employees/{assignId}', [\App\Http\Controllers\Admin\PayrollComponentController::class, 'removeAssignment'])->name('payroll-components.remove-assignment');

    // Employee Payrolls
    Route::get('/employee-payrolls', [\App\Http\Controllers\Admin\EmployeePayrollController::class, 'index'])->name('employee-payrolls.index');
    Route::get('/employee-payrolls/{id}/edit', [\App\Http\Controllers\Admin\EmployeePayrollController::class, 'edit'])->name('employee-payrolls.edit');
    Route::put('/employee-payrolls/{id}', [\App\Http\Controllers\Admin\EmployeePayrollController::class, 'updatePayroll'])->name('employee-payrolls.update-payroll');
    Route::post('/employee-payrolls/{id}/assign-component', [\App\Http\Controllers\Admin\EmployeePayrollController::class, 'assignComponent'])->name('employee-payrolls.assign-component');
    Route::put('/employee-payrolls/{employeeId}/components/{componentId}', [\App\Http\Controllers\Admin\EmployeePayrollController::class, 'updateComponent'])->name('employee-payrolls.update-component');
    Route::post('/employee-payrolls/{employeeId}/components/{componentId}/toggle', [\App\Http\Controllers\Admin\EmployeePayrollController::class, 'toggleComponent'])->name('employee-payrolls.toggle-component');
    Route::post('/employee-payrolls/bulk-assign', [\App\Http\Controllers\Admin\EmployeePayrollController::class, 'bulkAssign'])->name('employee-payrolls.bulk-assign');

    // Payroll Runs
    Route::get('/payroll-runs', [\App\Http\Controllers\Admin\PayrollRunController::class, 'index'])->name('payroll-runs.index');
    Route::post('/payroll-runs', [\App\Http\Controllers\Admin\PayrollRunController::class, 'store'])->name('payroll-runs.store');
    Route::get('/payroll-runs/{id}', [\App\Http\Controllers\Admin\PayrollRunController::class, 'show'])->name('payroll-runs.show');
    Route::put('/payroll-runs/{runId}/details/{detailId}', [\App\Http\Controllers\Admin\PayrollRunController::class, 'updateDetail'])->name('payroll-runs.update-detail');
    Route::post('/payroll-runs/{id}/finalize', [\App\Http\Controllers\Admin\PayrollRunController::class, 'finalize'])->name('payroll-runs.finalize');
    Route::post('/payroll-runs/{id}/publish', [\App\Http\Controllers\Admin\PayrollRunController::class, 'publish'])->name('payroll-runs.publish');
    Route::post('/payroll-runs/{id}/unpublish', [\App\Http\Controllers\Admin\PayrollRunController::class, 'unpublish'])->name('payroll-runs.unpublish');
    Route::post('/payroll-runs/{id}/lock', [\App\Http\Controllers\Admin\PayrollRunController::class, 'lock'])->name('payroll-runs.lock');
    Route::post('/payroll-runs/{id}/unlock', [\App\Http\Controllers\Admin\PayrollRunController::class, 'unlock'])->name('payroll-runs.unlock');
    Route::post('/payroll-runs/{id}/reopen', [\App\Http\Controllers\Admin\PayrollRunController::class, 'reopen'])->name('payroll-runs.reopen');
    Route::post('/payroll-runs/{id}/regenerate', [\App\Http\Controllers\Admin\PayrollRunController::class, 'regenerate'])->name('payroll-runs.regenerate');
    Route::post('/payroll-runs/{id}/inject-bpjs', [\App\Http\Controllers\Admin\PayrollRunController::class, 'injectBpjs'])->name('payroll-runs.inject-bpjs');
    Route::delete('/payroll-runs/{id}', [\App\Http\Controllers\Admin\PayrollRunController::class, 'destroy'])->name('payroll-runs.destroy');

    // Payslips
    Route::get('/payslips', [\App\Http\Controllers\Admin\PayslipController::class, 'index'])->name('payslips.index');
    Route::get('/payslips/{id}', [\App\Http\Controllers\Admin\PayslipController::class, 'show'])->name('payslips.show');
    Route::get('/payslips/{id}/download', [\App\Http\Controllers\Admin\PayslipController::class, 'downloadPdf'])->name('payslips.download');

    // Payroll Adjustments
    Route::get('/payroll-adjustments', [\App\Http\Controllers\Admin\PayrollAdjustmentController::class, 'index'])->name('payroll-adjustments.index');
    Route::get('/payroll-adjustments/create', [\App\Http\Controllers\Admin\PayrollAdjustmentController::class, 'create'])->name('payroll-adjustments.create');
    Route::post('/payroll-adjustments', [\App\Http\Controllers\Admin\PayrollAdjustmentController::class, 'store'])->name('payroll-adjustments.store');
    Route::get('/payroll-adjustments/bulk', [\App\Http\Controllers\Admin\PayrollAdjustmentController::class, 'bulkCreate'])->name('payroll-adjustments.bulk-create');
    Route::post('/payroll-adjustments/bulk', [\App\Http\Controllers\Admin\PayrollAdjustmentController::class, 'bulkStore'])->name('payroll-adjustments.bulk-store');
    Route::post('/payroll-adjustments/{id}/cancel', [\App\Http\Controllers\Admin\PayrollAdjustmentController::class, 'cancel'])->name('payroll-adjustments.cancel');
    Route::post('/payroll-adjustments/generate-backpay', [\App\Http\Controllers\Admin\PayrollAdjustmentController::class, 'generateBackpay'])->name('payroll-adjustments.generate-backpay');

    // Tax & BPJS
    Route::get('/tax/settings', [\App\Http\Controllers\Admin\TaxController::class, 'settings'])->name('tax.settings');
    Route::put('/tax/settings/{id}', [\App\Http\Controllers\Admin\TaxController::class, 'updateSetting'])->name('tax.update-setting');
    Route::put('/tax/bpjs-settings/{id}', [\App\Http\Controllers\Admin\TaxController::class, 'updateBpjsSetting'])->name('tax.update-bpjs-setting');
    Route::put('/tax/bpjs-settings', [\App\Http\Controllers\Admin\TaxController::class, 'updateBpjsAll'])->name('tax.update-bpjs-all');
    Route::get('/tax/simulator', [\App\Http\Controllers\Admin\TaxController::class, 'simulator'])->name('tax.simulator');
    Route::post('/tax/simulator', [\App\Http\Controllers\Admin\TaxController::class, 'simulate'])->name('tax.simulate');
    Route::get('/tax/bukti-potong', [\App\Http\Controllers\Admin\TaxController::class, 'buktiPotong'])->name('tax.bukti-potong');
    Route::post('/tax/bukti-potong/generate', [\App\Http\Controllers\Admin\TaxController::class, 'generateBuktiPotong'])->name('tax.generate-bukti-potong');
    Route::post('/tax/recalculate', [\App\Http\Controllers\Admin\TaxController::class, 'recalculate'])->name('tax.recalculate');
    Route::get('/tax/export-efiling', [\App\Http\Controllers\Admin\TaxController::class, 'exportEfiling'])->name('tax.export-efiling');

    // Reports
    Route::get('/reports', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/attendance', [\App\Http\Controllers\Admin\ReportController::class, 'attendance'])->name('reports.attendance');
    Route::get('/reports/attendance/export', [\App\Http\Controllers\Admin\ReportController::class, 'exportAttendance'])->name('reports.export-attendance');
    Route::get('/reports/leave', [\App\Http\Controllers\Admin\ReportController::class, 'leave'])->name('reports.leave');
    Route::get('/reports/leave/export', [\App\Http\Controllers\Admin\ReportController::class, 'exportLeave'])->name('reports.export-leave');
    Route::get('/reports/overtime', [\App\Http\Controllers\Admin\ReportController::class, 'overtime'])->name('reports.overtime');
    Route::get('/reports/overtime/export', [\App\Http\Controllers\Admin\ReportController::class, 'exportOvertime'])->name('reports.export-overtime');
    Route::get('/reports/payroll', [\App\Http\Controllers\Admin\ReportController::class, 'payroll'])->name('reports.payroll');
    Route::get('/reports/payroll/export', [\App\Http\Controllers\Admin\ReportController::class, 'exportPayroll'])->name('reports.export-payroll');
});


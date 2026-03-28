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
    Route::get('/employees/{id}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{id}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

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

    // Approval Rules
    Route::get('/approval-rules', [ApprovalRuleController::class, 'index'])->name('approval-rules.index');
    Route::post('/approval-rules', [ApprovalRuleController::class, 'store'])->name('approval-rules.store');
    Route::put('/approval-rules/{id}', [ApprovalRuleController::class, 'update'])->name('approval-rules.update');
    Route::delete('/approval-rules/{id}', [ApprovalRuleController::class, 'destroy'])->name('approval-rules.destroy');
    Route::post('/approval-rules/reorder', [ApprovalRuleController::class, 'reorder'])->name('approval-rules.reorder');
    Route::post('/approval-rules/{id}/toggle', [ApprovalRuleController::class, 'toggle'])->name('approval-rules.toggle');

    // Attendance Settings
    Route::get('/attendance-settings', [AttendanceSettingController::class, 'index'])->name('attendance-settings.index');
    Route::put('/attendance-settings', [AttendanceSettingController::class, 'update'])->name('attendance-settings.update');
});


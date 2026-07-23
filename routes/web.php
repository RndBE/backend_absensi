<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\ApprovalRuleController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendancePhotoArchiveController;
use App\Http\Controllers\Admin\AttendanceRecapController;
use App\Http\Controllers\Admin\AttendanceSettingController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BudgetPaymentController;
use App\Http\Controllers\Admin\BudgetRequestController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyRegulationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeApproverController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeePayrollController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\LeaveBalanceController;
use App\Http\Controllers\Admin\LeavePolicyController;
use App\Http\Controllers\Admin\LeaveRequestController;
use App\Http\Controllers\Admin\LeaveTypeController;
use App\Http\Controllers\Admin\LoanRequestController;
use App\Http\Controllers\Admin\MonitorApprovalController;
use App\Http\Controllers\Admin\PayrollAdjustmentController;
use App\Http\Controllers\Admin\PayrollComponentController;
use App\Http\Controllers\Admin\PayrollRunController;
use App\Http\Controllers\Admin\PayslipController;
use App\Http\Controllers\Admin\PolicyController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ScheduleTemplateController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\TravelReportController;
use App\Http\Controllers\Admin\TravelZoneController;
use App\Http\Controllers\Api\TravelZoneController as ApiTravelZoneController;
use App\Http\Controllers\Employee\AttendanceController as EmployeeAttendanceController;
use App\Http\Controllers\Employee\AttendanceRequestController as EmployeeAttendanceRequestController;
use App\Http\Controllers\Employee\ApprovalController as EmployeeApprovalController;
use App\Http\Controllers\Employee\TeamAttendanceController as EmployeeTeamAttendanceController;
use App\Http\Controllers\Employee\AuthController as EmployeeAuthController;
use App\Http\Controllers\Employee\BudgetRequestController as EmployeeBudgetRequestController;
use App\Http\Controllers\Employee\CompanyInfoController as EmployeeCompanyInfoController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\FacePhotoController as EmployeeFacePhotoController;
use App\Http\Controllers\Employee\LeaveController as EmployeeLeaveController;
use App\Http\Controllers\Employee\OvertimeController as EmployeeOvertimeController;
use App\Http\Controllers\Employee\PayslipController as EmployeePayslipController;
use App\Http\Controllers\Employee\ProfileController as EmployeeProfileController;
use App\Http\Controllers\Employee\TravelReportController as EmployeeTravelReportController;
use App\Http\Controllers\Employee\ViolationReportController as EmployeeViolationReportController;
use App\Http\Controllers\Employee\LpjController as EmployeeLpjController;
use App\Http\Controllers\Admin\LpjController;
use App\Http\Middleware\AdminActivityLogger;
use App\Http\Middleware\AdminAuth;
use App\Http\Middleware\AdminPermissionMiddleware;
use App\Http\Middleware\EmployeeAuth;
use Illuminate\Support\Facades\Route;

// Redirect root to employee portal
Route::get('/', fn () => redirect()->route('employee.login'));

// Employee Portal Auth
Route::get('/employee/login', [EmployeeAuthController::class, 'showLogin'])->name('employee.login');
Route::post('/employee/login', [EmployeeAuthController::class, 'login']);
Route::get('/employee/magic-login', [EmployeeAuthController::class, 'magicLogin'])->name('employee.magic-login');
Route::post('/employee/logout', [EmployeeAuthController::class, 'logout'])->name('employee.logout');

// Employee Portal Protected
Route::prefix('employee')->name('employee.')->middleware(EmployeeAuth::class)->group(function () {
    Route::get('/dashboard', [EmployeeDashboardController::class, 'index'])->name('dashboard');
    // Cek status sesi (JSON) → dipakai heartbeat untuk auto-redirect ke login saat sesi habis.
    Route::get('/session/check', fn () => response()->json(['ok' => true]))->name('session.check');
    Route::get('/profile', [EmployeeProfileController::class, 'show'])->name('profile.show');
    Route::get('/company-info', [EmployeeCompanyInfoController::class, 'index'])->name('company-info.index');
    Route::get('/company-info/regulations/{regulation}/download', [EmployeeCompanyInfoController::class, 'download'])->name('company-info.regulations.download');
    Route::get('/company-info/regulations/{regulation}/attachments/{attachment}/download', [EmployeeCompanyInfoController::class, 'downloadAttachment'])->name('company-info.regulations.attachments.download');
    Route::get('/violation-report', [EmployeeViolationReportController::class, 'index'])->name('violation-report.index');
    Route::get('/violation-report/open', [EmployeeViolationReportController::class, 'open'])->name('violation-report.open');
    Route::post('/profile/photo', [EmployeeProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [EmployeeProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::get('/profile/personal', [EmployeeProfileController::class, 'personal'])->name('profile.personal');
    Route::get('/profile/employment', [EmployeeProfileController::class, 'employment'])->name('profile.employment');
    Route::get('/profile/password', [EmployeeProfileController::class, 'editPassword'])->name('profile.password');
    Route::put('/profile/password', [EmployeeProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('/profile/data-change', [EmployeeProfileController::class, 'dataChange'])->name('profile.data-change');
    Route::post('/profile/data-change', [EmployeeProfileController::class, 'storeDataChange'])->name('profile.data-change.store');
    Route::get('/payslips', [EmployeePayslipController::class, 'index'])->name('payslips.index');
    Route::post('/payslips/unlock', [EmployeePayslipController::class, 'unlock'])->name('payslips.unlock');
    Route::get('/payslips/{id}/download', [EmployeePayslipController::class, 'downloadPdf'])->name('payslips.download');
    Route::get('/leaves', [EmployeeLeaveController::class, 'index'])->name('leaves.index');
    Route::get('/leaves/create', [EmployeeLeaveController::class, 'create'])->name('leaves.create');
    Route::post('/leaves', [EmployeeLeaveController::class, 'store'])->name('leaves.store');
    Route::get('/leaves/{id}/edit', [EmployeeLeaveController::class, 'edit'])->name('leaves.edit');
    Route::get('/leaves/{id}', [EmployeeLeaveController::class, 'show'])->name('leaves.show');
    Route::put('/leaves/{id}', [EmployeeLeaveController::class, 'update'])->name('leaves.update');
    Route::get('/overtimes', [EmployeeOvertimeController::class, 'index'])->name('overtimes.index');
    Route::get('/overtimes/create', [EmployeeOvertimeController::class, 'create'])->name('overtimes.create');
    Route::get('/overtimes/attendance-times', [EmployeeOvertimeController::class, 'attendanceTimes'])->name('overtimes.attendance-times');
    Route::get('/overtimes/day-type', [EmployeeOvertimeController::class, 'dayType'])->name('overtimes.day-type');
    Route::post('/overtimes', [EmployeeOvertimeController::class, 'store'])->name('overtimes.store');
    Route::get('/overtimes/{id}/edit', [EmployeeOvertimeController::class, 'edit'])->name('overtimes.edit');
    Route::put('/overtimes/{id}', [EmployeeOvertimeController::class, 'update'])->name('overtimes.update');
    Route::get('/overtimes/{id}', [EmployeeOvertimeController::class, 'show'])->name('overtimes.show');
    Route::get('/attendance-requests', [EmployeeAttendanceRequestController::class, 'index'])->name('attendance-requests.index');
    Route::get('/attendance-requests/create', [EmployeeAttendanceRequestController::class, 'create'])->name('attendance-requests.create');
    Route::post('/attendance-requests', [EmployeeAttendanceRequestController::class, 'store'])->name('attendance-requests.store');
    Route::get('/budget-requests', [EmployeeBudgetRequestController::class, 'index'])->name('budget-requests.index');
    Route::get('/budget-requests/create', [EmployeeBudgetRequestController::class, 'create'])->name('budget-requests.create');
    Route::post('/budget-requests', [EmployeeBudgetRequestController::class, 'store'])->name('budget-requests.store');
    Route::get('/budget-requests/{id}/edit', [EmployeeBudgetRequestController::class, 'edit'])->name('budget-requests.edit');
    Route::put('/budget-requests/{id}', [EmployeeBudgetRequestController::class, 'update'])->name('budget-requests.update');
    Route::get('/budget-requests/{id}', [EmployeeBudgetRequestController::class, 'show'])->name('budget-requests.show');
    Route::get('/travel/estimate-zone', [ApiTravelZoneController::class, 'estimateZone'])->name('travel.estimate-zone');
    Route::get('/travel-reports', [EmployeeTravelReportController::class, 'index'])->name('travel-reports.index');
    Route::get('/travel-reports/create', [EmployeeTravelReportController::class, 'create'])->name('travel-reports.create');
    Route::post('/travel-reports', [EmployeeTravelReportController::class, 'store'])->name('travel-reports.store');
    Route::get('/travel-reports/{id}', [EmployeeTravelReportController::class, 'show'])->name('travel-reports.show');
    Route::get('/travel-reports/{id}/edit', [EmployeeTravelReportController::class, 'edit'])->name('travel-reports.edit');
    Route::put('/travel-reports/{id}', [EmployeeTravelReportController::class, 'update'])->name('travel-reports.update');
    // LPJ
    Route::get('/lpj', [EmployeeLpjController::class, 'index'])->name('lpj.index');
    Route::get('/lpj/create', [EmployeeLpjController::class, 'create'])->name('lpj.create');
    Route::post('/lpj', [EmployeeLpjController::class, 'store'])->name('lpj.store');
    Route::get('/lpj/{id}', [EmployeeLpjController::class, 'show'])->name('lpj.show');
    // Presensi tim — hanya manager (dijaga di dalam controller: role + departemen).
    Route::get('/team-attendance', [EmployeeTeamAttendanceController::class, 'index'])->name('team-attendance.index');
    Route::get('/team-attendance/{employee}', [EmployeeTeamAttendanceController::class, 'show'])
        ->whereNumber('employee')
        ->name('team-attendance.show');

    Route::get('/approvals', [EmployeeApprovalController::class, 'index'])->name('approvals.index');
    Route::get('/approvals/budget/{id}/print', [EmployeeApprovalController::class, 'printBudget'])->name('approvals.budget.print');
    Route::get('/approvals/travel-report/{id}/print', [EmployeeApprovalController::class, 'printTravelReport'])->name('approvals.travel_report.print');
    Route::get('/approvals/lpj/{id}/print', [EmployeeApprovalController::class, 'printLpj'])->name('approvals.lpj.print');
    Route::post('/approvals/{type}/{id}/approve', [EmployeeApprovalController::class, 'approve'])
        ->whereIn('type', ['leave', 'overtime', 'attendance', 'budget', 'travel_report', 'lpj'])
        ->name('approvals.approve');
    Route::post('/approvals/{type}/{id}/reject', [EmployeeApprovalController::class, 'reject'])
        ->whereIn('type', ['leave', 'overtime', 'attendance', 'budget', 'travel_report', 'lpj'])
        ->name('approvals.reject');
    Route::get('/face-photo', [EmployeeFacePhotoController::class, 'show'])->name('face-photo.show');
    Route::post('/face-photo', [EmployeeFacePhotoController::class, 'store'])->name('face-photo.store');
    Route::delete('/face-photo', [EmployeeFacePhotoController::class, 'destroy'])->name('face-photo.destroy');
    Route::get('/attendance/{type}', [EmployeeAttendanceController::class, 'show'])
        ->whereIn('type', ['clock-in', 'clock-out'])
        ->name('attendance.show');
    Route::post('/attendance/clock-in', [EmployeeAttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('/attendance/clock-out', [EmployeeAttendanceController::class, 'clockOut'])->name('attendance.clock-out');
});

// Admin Auth
Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

// Admin Protected
Route::prefix('admin')->name('admin.')->middleware([
    AdminAuth::class,
    AdminPermissionMiddleware::class,
    AdminActivityLogger::class,
])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Employees
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::post('/employees/portal-link/send-all', [EmployeeController::class, 'sendPortalLinkToAll'])->name('employees.portal-link.send-all');
    Route::post('/employees/portal-link/whatsapp/send-all', [EmployeeController::class, 'sendPortalLinkWhatsAppToAll'])->name('employees.portal-link.whatsapp.send-all');
    Route::post('/employees/{id}/portal-link', [EmployeeController::class, 'sendPortalLink'])->name('employees.portal-link.send');
    Route::post('/employees/{id}/portal-link/whatsapp', [EmployeeController::class, 'sendPortalLinkWhatsApp'])->name('employees.portal-link.whatsapp.send');
    Route::get('/employees/{id}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('/employees/{id}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{id}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::get('/employees/{id}/resign', [EmployeeController::class, 'resign'])->name('employees.resign');
    Route::post('/employees/{id}/resign', [EmployeeController::class, 'processResign'])->name('employees.process-resign');

    // Employee Approver Chains
    Route::get('/employees/{id}/approvers', [EmployeeApproverController::class, 'index'])->name('employees.approvers.index');
    Route::post('/employees/{id}/approvers', [EmployeeApproverController::class, 'store'])->name('employees.approvers.store');

    // Departments
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::put('/departments/{id}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('/departments/{id}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

    // Attendance
    Route::get('/attendance/realtime', [AttendanceController::class, 'realtime'])->name('attendance.realtime');
    Route::get('/attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');
    Route::post('/attendance/{id}/security-review/approve', [AttendanceController::class, 'approveSecurityReview'])
        ->middleware(AdminPermissionMiddleware::class.':attendance.manage')
        ->name('attendance.security-review.approve');
    Route::post('/attendance/{id}/security-review/reject', [AttendanceController::class, 'rejectSecurityReview'])
        ->middleware(AdminPermissionMiddleware::class.':attendance.manage')
        ->name('attendance.security-review.reject');
    Route::get('/attendance-photo-archives', [AttendancePhotoArchiveController::class, 'index'])->name('attendance-photo-archives.index');
    Route::post('/attendance-photo-archives/generate', [AttendancePhotoArchiveController::class, 'generate'])->name('attendance-photo-archives.generate');
    Route::get('/attendance-photo-archives/{archive}/download', [AttendancePhotoArchiveController::class, 'download'])->name('attendance-photo-archives.download');
    Route::post('/attendance-photo-archives/{archive}/mark-uploaded', [AttendancePhotoArchiveController::class, 'markUploaded'])->name('attendance-photo-archives.mark-uploaded');

    // Leave Types (master data)
    Route::get('/leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
    Route::post('/leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
    Route::put('/leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('leave-types.update');
    Route::delete('/leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');

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
    Route::post('/leave-balances/bulk', [LeaveBalanceController::class, 'updateBulk'])->name('leave-balances.update-bulk');
    Route::put('/leave-balances/{leaveBalance}', [LeaveBalanceController::class, 'update'])->name('leave-balances.update');

    // Schedules
    Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
    Route::post('/schedules/bulk', [ScheduleController::class, 'bulkStore'])->name('schedules.bulk');
    Route::post('/schedules/clear', [ScheduleController::class, 'clearDay'])->name('schedules.clear');
    Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');

    // Attendance Recap
    Route::get('/attendance-recap', [AttendanceRecapController::class, 'index'])->name('attendance-recap.index');
    Route::post('/attendance-recap/import', [AttendanceRecapController::class, 'import'])->name('attendance-recap.import');
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

    // Monitor Approval (superadmin only)
    Route::get('/monitor-approvals', [MonitorApprovalController::class, 'index'])->name('monitor-approvals.index');

    // Approval Rules (Recap Dashboard)
    Route::get('/approval-rules', [ApprovalRuleController::class, 'index'])->name('approval-rules.index');
    Route::post('/approval-rules/bulk-assign', [ApprovalRuleController::class, 'bulkAssign'])->name('approval-rules.bulk-assign');

    // Budget Requests
    Route::get('/budget-requests', [BudgetRequestController::class, 'index'])->name('budget-requests.index');
    Route::get('/budget-requests/{id}/print', [BudgetRequestController::class, 'print'])->name('budget-requests.print');
    Route::get('/budget-requests/{id}', [BudgetRequestController::class, 'show'])->name('budget-requests.show');
    Route::put('/budget-requests/{id}/lhp-deadline', [BudgetRequestController::class, 'updateLhpDeadline'])->name('budget-requests.update-lhp-deadline');
    Route::delete('/budget-requests/{id}', [BudgetRequestController::class, 'destroy'])->name('budget-requests.destroy');

    // Pinjaman
    Route::get('/loan-requests', [LoanRequestController::class, 'index'])->name('loan-requests.index');
    Route::get('/loan-requests/create', [LoanRequestController::class, 'create'])->name('loan-requests.create');
    Route::post('/loan-requests', [LoanRequestController::class, 'store'])->name('loan-requests.store');
    Route::get('/loan-requests/{id}', [LoanRequestController::class, 'show'])->name('loan-requests.show');
    Route::get('/loan-requests/{id}/edit', [LoanRequestController::class, 'edit'])->name('loan-requests.edit');
    Route::put('/loan-requests/{id}', [LoanRequestController::class, 'update'])->name('loan-requests.update');
    Route::delete('/loan-requests/{id}', [LoanRequestController::class, 'destroy'])->name('loan-requests.destroy');

    // Travel Reports (LHP)
    Route::get('/travel-reports', [TravelReportController::class, 'index'])->name('travel-reports.index');
    Route::get('/travel-reports/create', [TravelReportController::class, 'create'])->name('travel-reports.create');
    Route::post('/travel-reports', [TravelReportController::class, 'store'])->name('travel-reports.store');
    Route::get('/travel-reports/{id}', [TravelReportController::class, 'show'])->name('travel-reports.show');
    Route::get('/travel-reports/{id}/edit', [TravelReportController::class, 'edit'])->name('travel-reports.edit');
    Route::put('/travel-reports/{id}', [TravelReportController::class, 'update'])->name('travel-reports.update');
    Route::get('/travel-reports/{id}/print', [TravelReportController::class, 'print'])->name('travel-reports.print');
    Route::delete('/travel-reports/{id}', [TravelReportController::class, 'destroy'])->name('travel-reports.destroy');

    // LPJ
    Route::get('/lpj', [LpjController::class, 'index'])->name('lpj.index');
    Route::get('/lpj/{id}', [LpjController::class, 'show'])->name('lpj.show');
    Route::get('/lpj/{id}/export-excel', [LpjController::class, 'exportExcel'])->name('lpj.export-excel');
    Route::post('/lpj/{id}/import-excel', [LpjController::class, 'importExcel'])->name('lpj.import-excel');
    Route::delete('/lpj/{id}', [LpjController::class, 'destroy'])->name('lpj.destroy');

    // Budget Payments
    Route::post('/budget-requests/{id}/payments', [BudgetPaymentController::class, 'store'])->name('budget-payments.store');
    Route::delete('/budget-requests/{requestId}/payments/{paymentId}', [BudgetPaymentController::class, 'destroy'])->name('budget-payments.destroy');

    // Policies (Reimbursement Rules)
    Route::get('/policies', [PolicyController::class, 'index'])->name('policies.index');
    Route::post('/policies', [PolicyController::class, 'store'])->name('policies.store');
    Route::put('/policies/{id}', [PolicyController::class, 'update'])->name('policies.update');
    Route::delete('/policies/{id}', [PolicyController::class, 'destroy'])->name('policies.destroy');

    // Travel Zones
    Route::get('/travel-zones', [TravelZoneController::class, 'index'])->name('travel-zones.index');
    Route::get('/travel-zones/detect', [TravelZoneController::class, 'detect'])->name('travel-zones.detect');
    Route::post('/travel-zones', [TravelZoneController::class, 'store'])->name('travel-zones.store');
    Route::put('/travel-zones/{id}', [TravelZoneController::class, 'update'])->name('travel-zones.update');
    Route::delete('/travel-zones/{id}', [TravelZoneController::class, 'destroy'])->name('travel-zones.destroy');

    // Company Settings
    Route::get('/company', [CompanyController::class, 'index'])->name('company.index');
    Route::put('/company', [CompanyController::class, 'update'])->name('company.update');
    Route::post('/company/regulations', [CompanyRegulationController::class, 'store'])->name('company.regulations.store');
    Route::post('/company/regulations/import', [CompanyRegulationController::class, 'import'])->name('company.regulations.import');
    Route::put('/company/regulations/{regulation}', [CompanyRegulationController::class, 'update'])->name('company.regulations.update');
    Route::delete('/company/regulations/{regulation}', [CompanyRegulationController::class, 'destroy'])->name('company.regulations.destroy');
    Route::get('/company/regulations/{regulation}/download', [CompanyRegulationController::class, 'download'])->name('company.regulations.download');
    Route::get('/company/regulations/{regulation}/attachments/{attachment}/download', [CompanyRegulationController::class, 'downloadAttachment'])->name('company.regulations.attachments.download');

    // Attendance Settings
    Route::get('/attendance-settings', [AttendanceSettingController::class, 'index'])->name('attendance-settings.index');
    Route::put('/attendance-settings', [AttendanceSettingController::class, 'update'])->name('attendance-settings.update');

    // Security
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::put('/roles/employees/{employee}', [RoleController::class, 'updateEmployee'])->name('roles.employees.update');
    Route::get('/role-permissions', [RolePermissionController::class, 'index'])->name('role-permissions.index');
    Route::put('/role-permissions/roles/{role}', [RolePermissionController::class, 'updateRole'])->name('role-permissions.roles.update');
    Route::put('/role-permissions/employees/{employee}', [RolePermissionController::class, 'updateEmployee'])->name('role-permissions.employees.update');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Payroll Components
    Route::get('/payroll-components', [PayrollComponentController::class, 'index'])->name('payroll-components.index');
    Route::post('/payroll-components', [PayrollComponentController::class, 'store'])->name('payroll-components.store');
    Route::post('/payroll-components/import-assignments', [PayrollComponentController::class, 'importAssignments'])->name('payroll-components.import-assignments');
    Route::put('/payroll-components/{id}', [PayrollComponentController::class, 'update'])->name('payroll-components.update');
    Route::post('/payroll-components/{id}/toggle', [PayrollComponentController::class, 'toggle'])->name('payroll-components.toggle');
    Route::delete('/payroll-components/{id}', [PayrollComponentController::class, 'destroy'])->name('payroll-components.destroy');
    // Assign employees to a component
    Route::get('/payroll-components/{id}/employees', [PayrollComponentController::class, 'employees'])->name('payroll-components.employees');
    Route::post('/payroll-components/{id}/employees', [PayrollComponentController::class, 'assignEmployee'])->name('payroll-components.assign-employee');
    Route::put('/payroll-components/{id}/employees/{assignId}', [PayrollComponentController::class, 'updateAssignment'])->name('payroll-components.update-assignment');
    Route::delete('/payroll-components/{id}/employees/{assignId}', [PayrollComponentController::class, 'removeAssignment'])->name('payroll-components.remove-assignment');

    // Employee Payrolls
    Route::get('/employee-payrolls', [EmployeePayrollController::class, 'index'])->name('employee-payrolls.index');
    Route::get('/employee-payrolls/{id}/edit', [EmployeePayrollController::class, 'edit'])->name('employee-payrolls.edit');
    Route::put('/employee-payrolls/{id}', [EmployeePayrollController::class, 'updatePayroll'])->name('employee-payrolls.update-payroll');
    Route::post('/employee-payrolls/{id}/assign-component', [EmployeePayrollController::class, 'assignComponent'])->name('employee-payrolls.assign-component');
    Route::put('/employee-payrolls/{employeeId}/components/{componentId}', [EmployeePayrollController::class, 'updateComponent'])->name('employee-payrolls.update-component');
    Route::post('/employee-payrolls/{employeeId}/components/{componentId}/toggle', [EmployeePayrollController::class, 'toggleComponent'])->name('employee-payrolls.toggle-component');
    Route::post('/employee-payrolls/bulk-assign', [EmployeePayrollController::class, 'bulkAssign'])->name('employee-payrolls.bulk-assign');

    // Payroll Runs
    Route::get('/payroll-runs', [PayrollRunController::class, 'index'])->name('payroll-runs.index');
    Route::post('/payroll-runs', [PayrollRunController::class, 'store'])->name('payroll-runs.store');
    Route::get('/payroll-runs/{id}', [PayrollRunController::class, 'show'])->name('payroll-runs.show');
    Route::put('/payroll-runs/{runId}/details/{detailId}', [PayrollRunController::class, 'updateDetail'])->name('payroll-runs.update-detail');
    Route::post('/payroll-runs/{id}/finalize', [PayrollRunController::class, 'finalize'])->name('payroll-runs.finalize');
    Route::post('/payroll-runs/{id}/publish', [PayrollRunController::class, 'publish'])->name('payroll-runs.publish');
    Route::post('/payroll-runs/{id}/unpublish', [PayrollRunController::class, 'unpublish'])->name('payroll-runs.unpublish');
    Route::post('/payroll-runs/{id}/lock', [PayrollRunController::class, 'lock'])->name('payroll-runs.lock');
    Route::post('/payroll-runs/{id}/unlock', [PayrollRunController::class, 'unlock'])->name('payroll-runs.unlock');
    Route::post('/payroll-runs/{id}/reopen', [PayrollRunController::class, 'reopen'])->name('payroll-runs.reopen');
    Route::post('/payroll-runs/{id}/regenerate', [PayrollRunController::class, 'regenerate'])->name('payroll-runs.regenerate');
    Route::post('/payroll-runs/{id}/inject-bpjs', [PayrollRunController::class, 'injectBpjs'])->name('payroll-runs.inject-bpjs');
    Route::delete('/payroll-runs/{id}', [PayrollRunController::class, 'destroy'])->name('payroll-runs.destroy');

    // Payslips
    Route::get('/payslips', [PayslipController::class, 'index'])->name('payslips.index');
    Route::post('/payslips/import', [PayslipController::class, 'import'])->name('payslips.import');
    Route::get('/payslips/run/{runId}/download', [PayslipController::class, 'downloadRunBundle'])->name('payslips.download-run');
    Route::get('/payslips/{id}', [PayslipController::class, 'show'])->name('payslips.show');
    Route::get('/payslips/{id}/download', [PayslipController::class, 'downloadPdf'])->name('payslips.download');

    // Payroll Adjustments
    Route::get('/payroll-adjustments', [PayrollAdjustmentController::class, 'index'])->name('payroll-adjustments.index');
    Route::get('/payroll-adjustments/create', [PayrollAdjustmentController::class, 'create'])->name('payroll-adjustments.create');
    Route::post('/payroll-adjustments', [PayrollAdjustmentController::class, 'store'])->name('payroll-adjustments.store');
    Route::get('/payroll-adjustments/bulk', [PayrollAdjustmentController::class, 'bulkCreate'])->name('payroll-adjustments.bulk-create');
    Route::post('/payroll-adjustments/bulk', [PayrollAdjustmentController::class, 'bulkStore'])->name('payroll-adjustments.bulk-store');
    Route::post('/payroll-adjustments/{id}/cancel', [PayrollAdjustmentController::class, 'cancel'])->name('payroll-adjustments.cancel');
    Route::post('/payroll-adjustments/generate-backpay', [PayrollAdjustmentController::class, 'generateBackpay'])->name('payroll-adjustments.generate-backpay');

    // Tax & BPJS
    Route::get('/tax/settings', [TaxController::class, 'settings'])->name('tax.settings');
    Route::put('/tax/settings/{id}', [TaxController::class, 'updateSetting'])->name('tax.update-setting');
    Route::put('/tax/bpjs-settings/{id}', [TaxController::class, 'updateBpjsSetting'])->name('tax.update-bpjs-setting');
    Route::put('/tax/bpjs-settings', [TaxController::class, 'updateBpjsAll'])->name('tax.update-bpjs-all');
    Route::get('/tax/simulator', [TaxController::class, 'simulator'])->name('tax.simulator');
    Route::post('/tax/simulator', [TaxController::class, 'simulate'])->name('tax.simulate');
    Route::get('/tax/bukti-potong', [TaxController::class, 'buktiPotong'])->name('tax.bukti-potong');
    Route::post('/tax/bukti-potong/generate', [TaxController::class, 'generateBuktiPotong'])->name('tax.generate-bukti-potong');
    Route::get('/tax/bukti-potong/{id}', [TaxController::class, 'showBuktiPotong'])->name('tax.show-bukti-potong');
    Route::get('/tax/bukti-potong/{id}/download', [TaxController::class, 'downloadBuktiPotong'])->name('tax.download-bukti-potong');
    Route::post('/tax/bukti-potong/{id}/finalize', [TaxController::class, 'finalizeBuktiPotong'])->name('tax.finalize-bukti-potong');
    Route::post('/tax/recalculate', [TaxController::class, 'recalculate'])->name('tax.recalculate');
    Route::get('/tax/export-efiling', [TaxController::class, 'exportEfiling'])->name('tax.export-efiling');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');
    Route::get('/reports/attendance/export', [ReportController::class, 'exportAttendance'])->name('reports.export-attendance');
    Route::get('/reports/leave', [ReportController::class, 'leave'])->name('reports.leave');
    Route::get('/reports/leave/export', [ReportController::class, 'exportLeave'])->name('reports.export-leave');
    Route::get('/reports/overtime', [ReportController::class, 'overtime'])->name('reports.overtime');
    Route::get('/reports/overtime/export', [ReportController::class, 'exportOvertime'])->name('reports.export-overtime');
    Route::get('/reports/payroll', [ReportController::class, 'payroll'])->name('reports.payroll');
    Route::get('/reports/payroll/export', [ReportController::class, 'exportPayroll'])->name('reports.export-payroll');
});

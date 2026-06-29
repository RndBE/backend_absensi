<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\DailyReportPrefillController;
use App\Http\Controllers\Api\DailyTokenController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TravelReportController;
use App\Http\Controllers\Api\TravelZoneController;
use App\Http\Controllers\Api\Tessa\TessaController;
use App\Http\Controllers\Api\Tessa\TessaActionController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/verify-password', [AuthController::class, 'verifyPassword']);
    Route::put('/auth/change-password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // DailyCloseApp bridge
    Route::get('/daily/token', [DailyTokenController::class, 'issue']);
    Route::get('/daily/prefill', [DailyReportPrefillController::class, 'show']);

    // Company Info
    Route::get('/company', [DashboardController::class, 'companyInfo']);

    // Attendance
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut']);
    Route::get('/attendance/settings', [AttendanceController::class, 'settings']);
    Route::get('/attendance/history', [AttendanceController::class, 'history']);
    Route::get('/attendance/recap', [AttendanceController::class, 'recap']);
    Route::get('/attendance/schedule', [AttendanceController::class, 'schedule']);
    Route::get('/attendance/{id}', [AttendanceController::class, 'show']);

    // Attendance Requests (Pengajuan Presensi)
    Route::get('/attendance-requests', [AttendanceRequestController::class, 'index']);
    Route::post('/attendance-requests', [AttendanceRequestController::class, 'store']);
    Route::get('/attendance-requests/{id}', [AttendanceRequestController::class, 'show']);

    // Leave (Cuti)
    Route::get('/leave/balance', [LeaveController::class, 'balance']);
    Route::get('/leave/types', [LeaveController::class, 'types']);
    Route::get('/leave/company-timeline', [LeaveController::class, 'companyTimeline']);
    Route::get('/leave/requests', [LeaveController::class, 'index']);
    Route::post('/leave/requests', [LeaveController::class, 'store']);
    Route::get('/leave/requests/{id}', [LeaveController::class, 'show']);

    // Overtime (Lembur)
    Route::get('/overtime/requests', [OvertimeController::class, 'index']);
    Route::post('/overtime/requests', [OvertimeController::class, 'store']);
    Route::get('/overtime/check-shift', [OvertimeController::class, 'checkShift']);
    Route::get('/overtime/requests/{id}', [OvertimeController::class, 'show']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // Approvals
    Route::get('/approvals/monitor', [ApprovalController::class, 'monitor']);
    Route::get('/approvals', [ApprovalController::class, 'index']);
    Route::get('/approvals/{type}/{id}', [ApprovalController::class, 'show']);
    Route::post('/approvals/{type}/{id}/approve', [ApprovalController::class, 'approve']);
    Route::post('/approvals/{type}/{id}/reject', [ApprovalController::class, 'reject']);

    // Budget / Reimbursement
    Route::get('/budget/requests', [BudgetController::class, 'index']);
    Route::post('/budget/requests', [BudgetController::class, 'store']);
    Route::get('/budget/item-types', [BudgetController::class, 'itemTypes']);
    Route::get('/budget/detect-zone', [BudgetController::class, 'detectZone']);
    Route::get('/budget/requests/{id}', [BudgetController::class, 'show']);

    // Travel Zone
    Route::get('/travel/estimate-zone', [TravelZoneController::class, 'estimateZone']);

    // Travel Reports (LHP)
    Route::get('/travel-reports', [TravelReportController::class, 'index']);
    Route::post('/travel-reports', [TravelReportController::class, 'store']);
    Route::put('/travel-reports/{id}', [TravelReportController::class, 'update']);
    Route::get('/travel-reports/available-requests', [TravelReportController::class, 'availableRequests']);
    Route::get('/travel-reports/{id}', [TravelReportController::class, 'show']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::get('/profile/personal', [ProfileController::class, 'personal']);
    Route::get('/profile/employment', [ProfileController::class, 'employment']);
    Route::post('/profile/fcm-token', [ProfileController::class, 'updateFcmToken']);
    Route::post('/profile/face-photo', [ProfileController::class, 'uploadFacePhoto']);
    Route::delete('/profile/face-photo', [ProfileController::class, 'deleteFacePhoto']);

    // Data Change Requests (Perubahan Data)
    Route::post('/data-change-requests', [ProfileController::class, 'requestDataChange']);
    Route::get('/data-change-requests', [ProfileController::class, 'dataChangeRequests']);

    // Employees (Pegawai)
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);

    // Payslips
    Route::get('/payslips', [PayslipController::class, 'index']);
    Route::get('/payslips/{id}', [PayslipController::class, 'show']);
    Route::get('/payslips/{id}/download', [PayslipController::class, 'downloadPdf']);
});

/*
|--------------------------------------------------------------------------
| Tessa (AI kantor) — API key statis, read + sebagian aksi.
| Tidak ada endpoint payroll/slip gaji di sini (lihat middleware tessa.api).
|--------------------------------------------------------------------------
*/
Route::middleware('tessa.api')->prefix('tessa')->group(function () {
    Route::get('/ping', [TessaController::class, 'ping']);

    // Karyawan & profil (tanpa gaji)
    Route::get('/employees', [TessaController::class, 'employees']);
    Route::get('/employees/{id}', [TessaController::class, 'employee']);

    // Presensi & jadwal
    Route::get('/attendance', [TessaController::class, 'attendance']);
    Route::get('/attendance/recap', [TessaController::class, 'attendanceRecap']);

    // Cuti, lembur & pengajuan
    Route::get('/leaves', [TessaController::class, 'leaves']);
    Route::get('/overtimes', [TessaController::class, 'overtimes']);
    Route::get('/attendance-requests', [TessaController::class, 'attendanceRequests']);
    Route::get('/budget-requests', [TessaController::class, 'budgetRequests']);
    Route::get('/travel-reports', [TessaController::class, 'travelReports']);
    Route::get('/lpj', [TessaController::class, 'lpj']);
    Route::get('/approvals/summary', [TessaController::class, 'approvalsSummary']);

    // Perusahaan & pengumuman
    Route::get('/company', [TessaController::class, 'company']);
    Route::get('/announcements', [TessaController::class, 'announcements']);

    // Penjadwalan (shift)
    Route::get('/shifts', [TessaController::class, 'shifts']);

    // Aksi: notifikasi & jadwal harian
    Route::post('/notifications', [TessaController::class, 'sendNotification']);
    Route::post('/schedules', [TessaController::class, 'assignSchedules']);

    // Aksi: approve / reject pengajuan (mendukung dry_run)
    Route::post('/approvals/{type}/{id}/approve', [TessaActionController::class, 'approve']);
    Route::post('/approvals/{type}/{id}/reject', [TessaActionController::class, 'reject']);

    // Aksi: ubah data karyawan (lewat pengajuan yang disetujui superadmin)
    Route::post('/data-change-requests', [TessaActionController::class, 'requestDataChange']);

    // Aksi: master jadwal (shift & template)
    Route::post('/shifts', [TessaActionController::class, 'createShift']);
    Route::put('/shifts/{id}', [TessaActionController::class, 'updateShift']);
    Route::post('/schedule-templates', [TessaActionController::class, 'createTemplate']);
    Route::post('/schedule-templates/assign', [TessaActionController::class, 'assignTemplate']);

    // Aksi: buat pengajuan atas nama karyawan
    Route::post('/requests/{type}', [TessaActionController::class, 'createRequest']);
});

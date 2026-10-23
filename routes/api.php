<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\ApprovalHistoryController;
use App\Http\Controllers\Api\AssignmentLetterApprovalController;
use App\Http\Controllers\Api\AssignmentLetterController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\DailyReportPrefillController;
use App\Http\Controllers\Api\DailyTokenController;
use App\Http\Controllers\Api\CompanyInfoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\HrContactController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\LpjController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\Api\PegawaiLookupController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TeamAttendanceController;
use App\Http\Controllers\Api\TravelReportController;
use App\Http\Controllers\Api\TravelZoneController;
use App\Http\Controllers\Api\Tessa\TessaController;
use App\Http\Controllers\Api\Tessa\TessaActionController;
use App\Http\Controllers\Api\Tessa\TessaReminderController;
use App\Http\Controllers\Api\Tessa\TessaSessionController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);

// Kontak bantuan HRD untuk halaman login (belum ada token): hanya nomor WhatsApp & email, dibatasi laju.
Route::get('/hr-contact', [HrContactController::class, 'show'])->middleware('throttle:30,1');

/*
|--------------------------------------------------------------------------
| Service-to-service (X-API-KEY)
|--------------------------------------------------------------------------
| Untuk sistem lain yang membaca data HRIS tanpa menyalin tabelnya — saat ini
| inventory, yang butuh identitas pegawai untuk pencatatan aset.
| Hanya baca, hanya identitas dasar. Tidak ada payroll di sini.
| Dokumentasi: docs/api-pegawai-by-email.md
*/
Route::middleware('service.api')->group(function () {
    Route::get('/pegawai/by-email', [PegawaiLookupController::class, 'byEmail']);
});

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
    Route::get('/company-info', [CompanyInfoController::class, 'index']);
    Route::get('/company-info/regulations/{regulation}/attachments/{attachment}/link', [CompanyInfoController::class, 'attachmentLink'])
        ->whereNumber(['regulation', 'attachment']);

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
    Route::get('/approvals/history', [ApprovalHistoryController::class, 'index']);
    Route::get('/approvals', [ApprovalController::class, 'index']);
    Route::get('/approvals/{type}/{id}', [ApprovalController::class, 'show']);
    Route::post('/approvals/{type}/{id}/approve', [ApprovalController::class, 'approve']);
    Route::post('/approvals/{type}/{id}/reject', [ApprovalController::class, 'reject']);
    Route::post('/approvals/lpj/{id}/transfer-date', [ApprovalController::class, 'saveLpjTransferDate'])->whereNumber('id');

    // Budget / Reimbursement
    Route::get('/budget/requests', [BudgetController::class, 'index']);
    Route::post('/budget/requests', [BudgetController::class, 'store']);
    Route::get('/budget/item-types', [BudgetController::class, 'itemTypes']);
    Route::get('/budget/detect-zone', [BudgetController::class, 'detectZone']);
    Route::get('/budget/assignment-letters', [BudgetController::class, 'assignmentLetters']);
    Route::get('/budget/requests/{id}', [BudgetController::class, 'show']);

    // Surat tugas yang mencantumkan karyawan (bukan draft)
    Route::get('/assignment-letters', [AssignmentLetterController::class, 'index']);
    Route::get('/assignment-letters/{id}', [AssignmentLetterController::class, 'show'])->whereNumber('id');
    // Paraf digital surat tugas oleh atasan L3/L2, HRD, dan GA HSE; antreannya ada di GET /approvals.
    Route::get('/assignment-approvals/{id}', [AssignmentLetterApprovalController::class, 'show'])->whereNumber('id');
    Route::post('/assignment-approvals/{id}/approve', [AssignmentLetterApprovalController::class, 'approve'])->whereNumber('id');
    Route::post('/assignment-approvals/{id}/reject', [AssignmentLetterApprovalController::class, 'reject'])->whereNumber('id');

    // Travel Zone
    Route::get('/travel/estimate-zone', [TravelZoneController::class, 'estimateZone']);

    // Travel Reports (LHP)
    Route::get('/travel-reports', [TravelReportController::class, 'index']);
    Route::post('/travel-reports', [TravelReportController::class, 'store']);
    Route::put('/travel-reports/{id}', [TravelReportController::class, 'update']);
    Route::get('/travel-reports/available-requests', [TravelReportController::class, 'availableRequests']);
    Route::get('/travel-reports/{id}', [TravelReportController::class, 'show']);

    // LPJ
    Route::get('/lpj', [LpjController::class, 'index']);
    Route::post('/lpj', [LpjController::class, 'store']);
    Route::get('/lpj/available-requests', [LpjController::class, 'availableRequests']);
    Route::get('/lpj/{id}', [LpjController::class, 'show'])->whereNumber('id');

    // Profile
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::get('/profile/personal', [ProfileController::class, 'personal']);
    Route::get('/profile/employment', [ProfileController::class, 'employment']);
    Route::post('/profile/fcm-token', [ProfileController::class, 'updateFcmToken']);
    Route::post('/profile/face-photo', [ProfileController::class, 'uploadFacePhoto']);
    Route::delete('/profile/face-photo', [ProfileController::class, 'deleteFacePhoto']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);
    Route::post('/profile/signature', [ProfileController::class, 'uploadSignature']);
    Route::delete('/profile/signature', [ProfileController::class, 'deleteSignature']);

    // Data Change Requests (Perubahan Data)
    Route::post('/data-change-requests', [ProfileController::class, 'requestDataChange']);
    Route::get('/data-change-requests', [ProfileController::class, 'dataChangeRequests']);

    // Employees (Pegawai)
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);

    // Presensi tim (level 1-3 / hr_admin; dijaga di dalam controller)
    Route::get('/team-attendance', [TeamAttendanceController::class, 'index']);
    Route::get('/team-attendance/{id}', [TeamAttendanceController::class, 'show'])->whereNumber('id');

    // Payslips
    Route::get('/payslips', [PayslipController::class, 'index']);
    Route::get('/payslips/{id}', [PayslipController::class, 'show']);
    Route::get('/payslips/{id}/download', [PayslipController::class, 'downloadPdf']);
});

/*
|--------------------------------------------------------------------------
| Tessa (AI kantor) — mengikuti role HRIS.
| /ping & /session dilindungi service key (TESSA_API_KEY). Sisanya memakai token
| per-user (Sanctum) → aktor = karyawan pemilik token, kapabilitas ikut role-nya.
| Payroll/slip gaji diblokir total (lihat middleware tessa.actor).
|--------------------------------------------------------------------------
*/
Route::middleware('tessa.service')->prefix('tessa')->group(function () {
    Route::get('/ping', [TessaController::class, 'ping']);
    // Login karyawan → token Tessa (identitas & role terikat ke akunnya).
    Route::post('/session', [TessaSessionController::class, 'login']);
    // Reminder sistem (clockin/lhp/lpj) yang jatuh tempo → Tessa kirim via WhatsApp.
    Route::get('/reminders/due', [TessaReminderController::class, 'due']);
    // Pengajuan menunggu approval + approver step aktif → Tessa WA approver-nya.
    Route::get('/approvals/pending', [TessaReminderController::class, 'pendingApprovals']);
    // Hasil final approval → Tessa WA pengaju-nya.
    Route::get('/approvals/results', [TessaReminderController::class, 'approvalResults']);
});

Route::middleware(['auth:sanctum', 'tessa.actor'])->prefix('tessa')->group(function () {
    Route::post('/session/logout', [TessaSessionController::class, 'logout']);

    // Karyawan & profil (tanpa gaji)
    Route::get('/employees', [TessaController::class, 'employees']);
    Route::get('/employees/{id}', [TessaController::class, 'employee']);

    // Presensi & jadwal
    Route::get('/attendance', [TessaController::class, 'attendance']);
    Route::get('/attendance/recap', [TessaController::class, 'attendanceRecap']);
    Route::get('/schedules', [TessaController::class, 'schedules']);

    // Cuti, lembur & pengajuan
    Route::get('/leave-balances', [TessaController::class, 'leaveBalances']);
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
    Route::post('/schedules/import', [TessaController::class, 'importSchedules']);
    Route::post('/schedules', [TessaController::class, 'assignSchedules']);

    // Aksi: approve / reject pengajuan (mendukung dry_run)
    Route::get('/approvals/{type}/{id}/next', [TessaController::class, 'nextApprover']);
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
    Route::put('/requests/{type}/{id}', [TessaActionController::class, 'updateRequest']);
});

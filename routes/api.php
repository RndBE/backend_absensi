<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/change-password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

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
    Route::get('/leave/requests', [LeaveController::class, 'index']);
    Route::post('/leave/requests', [LeaveController::class, 'store']);
    Route::get('/leave/requests/{id}', [LeaveController::class, 'show']);

    // Overtime (Lembur)
    Route::get('/overtime/requests', [OvertimeController::class, 'index']);
    Route::post('/overtime/requests', [OvertimeController::class, 'store']);
    Route::get('/overtime/requests/{id}', [OvertimeController::class, 'show']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // Approvals
    Route::get('/approvals', [ApprovalController::class, 'index']);
    Route::get('/approvals/{type}/{id}', [ApprovalController::class, 'show']);
    Route::post('/approvals/{type}/{id}/approve', [ApprovalController::class, 'approve']);
    Route::post('/approvals/{type}/{id}/reject', [ApprovalController::class, 'reject']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::get('/profile/personal', [ProfileController::class, 'personal']);
    Route::get('/profile/employment', [ProfileController::class, 'employment']);
    Route::post('/profile/fcm-token', [ProfileController::class, 'updateFcmToken']);

    // Data Change Requests (Perubahan Data)
    Route::post('/data-change-requests', [ProfileController::class, 'requestDataChange']);
    Route::get('/data-change-requests', [ProfileController::class, 'dataChangeRequests']);

    // Employees (Pegawai)
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);

    // Payslips
    Route::get('/payslips', [\App\Http\Controllers\Api\PayslipController::class, 'index']);
    Route::get('/payslips/{id}', [\App\Http\Controllers\Api\PayslipController::class, 'show']);
    Route::get('/payslips/{id}/download', [\App\Http\Controllers\Api\PayslipController::class, 'downloadPdf']);
});

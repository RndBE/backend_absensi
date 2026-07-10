<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Models\ScheduleAssignment;
use App\Models\Setting;
use App\Services\FaceVerificationService;
use App\Services\FcmService;
use App\Support\AdminPermission;
use App\Support\AttendanceOpenShift;
use App\Support\AttendanceLateExcuse;
use App\Support\MonthlyAttendance;
use App\Support\AttendanceLeaveSync;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    public function history(Request $request)
    {
        $request->validate([
            'period' => 'nullable|date_format:Y-m',
        ]);

        $period = $request->period ? Carbon::parse($request->period . '-01') : now();
        $employee = $request->user();

        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereYear('date', $period->year)
            ->whereMonth('date', $period->month)
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attendances,
        ]);
    }

    public function recap(Request $request)
    {
        $request->validate([
            'period' => 'nullable|date_format:Y-m',
        ]);

        $period = $request->period ? Carbon::parse($request->period . '-01') : now();
        $employee = $request->user();

        $query = Attendance::where('employee_id', $employee->id)
            ->whereYear('date', $period->year)
            ->whereMonth('date', $period->month);
        $startOfMonth = $period->copy()->startOfMonth();
        $endOfMonth = $period->copy()->endOfMonth();
        $lateExcuseDates = AttendanceLateExcuse::lateExcuseDates(
            LeaveRequest::with('leaveType')
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->where('start_date', '<=', $endOfMonth->toDateString())
                ->where('end_date', '>=', $startOfMonth->toDateString())
                ->get(),
            $startOfMonth,
            $endOfMonth
        );

        // 'late_excuse' & 'early_departure' = izin parsial, tetap dihitung hadir.
        $hadir = (clone $query)->whereIn('status', ['present', 'late_excuse', 'early_departure'])->count();
        $absen = (clone $query)->where('status', 'absent')->count();
        $terlambat = (clone $query)
            ->where('is_late', true)
            ->when($lateExcuseDates->isNotEmpty(), fn ($q) => $q->whereNotIn('date', $lateExcuseDates->keys()->all()))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period->format('Y-m'),
                'hadir' => $hadir,
                'absen' => $absen,
                'terlambat' => $terlambat,
            ],
        ]);
    }

    public function schedule(Request $request)
    {
        $request->validate([
            'period' => 'nullable|date_format:Y-m',
        ]);

        $period = $request->period ? Carbon::parse($request->period . '-01') : now();
        $employee = $request->user();

        // Aturan "hari ini dihitung apa" hidup di App\Support\MonthlyAttendance agar sama
        // persis dengan tampilan rekap tim di portal.
        ['stats' => $stats, 'days' => $days] = MonthlyAttendance::build($employee, $period);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period->format('Y-m'),
                'template_name' => $employee->scheduleTemplate->name ?? null,
                'stats' => $stats,
                'days' => $days,
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $attendance = Attendance::where('employee_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $attendance,
        ]);
    }

    public function clockIn(Request $request)
    {
        $requirePhoto = Setting::getValue('require_photo', '1') === '1';
        $requireGps = Setting::getValue('require_gps', '1') === '1';
        $allowRemote = Setting::getValue('allow_remote_clockin', '0') === '1';
        $remoteNeedsNotes = Setting::getValue('remote_requires_notes', '1') === '1';

        $rules = [
            'latitude' => $requireGps ? 'required|numeric' : 'nullable|numeric',
            'longitude' => $requireGps ? 'required|numeric' : 'nullable|numeric',
            'location_accuracy' => 'nullable|numeric|min:0',
            'accuracy' => 'nullable|numeric|min:0',
            'is_mock_location' => 'nullable|boolean',
            'is_mocked' => 'nullable|boolean',
            'location_is_mocked' => 'nullable|boolean',
            'location_timestamp' => 'nullable|date',
            'photo' => 'nullable|image|max:5120',
            'photo_base64' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
        ];

        // Either photo file or photo_base64 must be present if required
        if ($requirePhoto && !$request->hasFile('photo') && !$request->photo_base64) {
            return response()->json([
                'success' => false,
                'message' => 'Foto selfie wajib diambil.',
            ], 422);
        }

        $request->validate($rules);

        $employee = $request->user();
        $today = Carbon::today();

        // Blok clock-in baru hanya jika sesi kemarin memang shift overnight.
        $openOvernight = Attendance::where('employee_id', $employee->id)
            ->where('date', Carbon::yesterday()->toDateString())
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->first();

        if ($openOvernight && AttendanceOpenShift::isOvernight($employee, Carbon::yesterday())) {
            return response()->json([
                'success' => false,
                'message' => 'Anda masih memiliki sesi shift malam yang belum clock out. Silakan clock out terlebih dahulu.',
            ], 422);
        }

        // Check if already clocked in today
        $existing = Attendance::where('employee_id', $employee->id)
            ->where('date', $today->toDateString())
            ->first();

        if ($existing && $existing->clock_in) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah clock in hari ini.',
            ], 422);
        }

        // GPS Radius Check
        $isRemote = false;
        $distance = null;
        if ($requireGps && $request->latitude && $request->longitude) {
            ['distance' => $distance, 'radius' => $radius] = $this->officeDistance($request);

            if ($distance > $radius) {
                if (!$allowRemote) {
                    return response()->json([
                        'success' => false,
                        'message' => "Anda berada di luar radius kantor ({$radius}m). Clock-in remote tidak diizinkan.",
                        'distance' => round($distance),
                        'radius' => $radius,
                    ], 422);
                }

                // Remote clock-in allowed
                $isRemote = true;

                if ($remoteNeedsNotes && empty($request->notes)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Clock-in remote wajib mengisi catatan/alasan.',
                        'distance' => round($distance),
                        'radius' => $radius,
                    ], 422);
                }
            }
        }

        // Store selfie photo
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('attendance/clock-in', 'public');
        } elseif ($request->photo_base64) {
            $photoPath = $this->storeBase64Photo($request->photo_base64, 'attendance/clock-in');
        }

        // Face Verification: compare selfie with registered face photo
        $faceVerify = Setting::getValue('face_verification_enabled', '1') === '1';
        if ($faceVerify) {
            if (!$employee->face_photo) {
                if ($photoPath) Storage::disk('public')->delete($photoPath);
                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum mendaftarkan foto verifikasi wajah. Silakan daftarkan di menu Akun → Verifikasi Wajah terlebih dahulu.',
                ], 422);
            }

            if ($photoPath) {
                $result = (new FaceVerificationService())->verify($photoPath, $employee->face_photo);

                if (!$result['match']) {
                    Storage::disk('public')->delete($photoPath);
                    return response()->json([
                        'success'    => false,
                        'message'    => $result['message'],
                        'similarity' => isset($result['similarity']) ? round($result['similarity'] * 100, 1) : null,
                    ], 422);
                }
            }
        }

        // Check if late
        $isLate = false;
        $shiftStartTime = $this->getShiftStartTime($employee, $today);
        if ($shiftStartTime) {
            $scheduleStart = Carbon::parse($today->toDateString() . ' ' . $shiftStartTime);
            $clockInMinute = now()->copy()->startOfMinute();
            $scheduleStartMinute = $scheduleStart->copy()->startOfMinute();
            $isLate = $clockInMinute->gt($scheduleStartMinute);
        }

        // Jika ada izin datang terlambat yang sudah di-ACC untuk hari ini,
        // simpan langsung sebagai status izin (tetap dihitung hadir).
        $clockInStatus = AttendanceLeaveSync::approvedTargetForDate($employee, $today) === AttendanceLateExcuse::LATE_EXCUSE_STATUS
            ? AttendanceLateExcuse::LATE_EXCUSE_STATUS
            : 'present';

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today->toDateString()],
            [
                'clock_in' => now()->format('H:i:s'),
                'clock_in_lat' => $request->latitude,
                'clock_in_lng' => $request->longitude,
                ...$this->locationAuditAttributes($request, 'clock_in'),
                ...$this->locationReviewAttributes($request, 'clock_in', $requireGps),
                'clock_in_photo' => $photoPath,
                'status' => $clockInStatus,
                'is_late' => $isLate,
                'is_remote' => $isRemote,
                'remote_notes' => $isRemote ? $request->notes : null,
            ]
        );

        $this->notifyHrAdminsOfClockIn($attendance);

        $needsReview = $attendance->review_status === 'pending';
        if ($needsReview) {
            $this->notifyAttendanceSecurityReview($attendance, 'clock_in');
        }

        $message = 'Clock in berhasil';
        if ($isRemote) {
            $message .= ' (remote — ' . round($distance) . 'm dari kantor)';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $attendance,
            'is_remote' => $isRemote,
            'distance' => $distance ? round($distance) : null,
            'needs_review' => $needsReview,
            'review_status' => $attendance->review_status,
        ]);
    }

    /**
     * Calculate distance between two GPS coordinates using Haversine formula.
     */
    private function haversineDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private function locationIsMocked(Request $request): bool
    {
        return $request->boolean('is_mock_location')
            || $request->boolean('is_mocked')
            || $request->boolean('location_is_mocked');
    }

    private function locationAccuracy(Request $request): ?float
    {
        $accuracy = $request->input('location_accuracy', $request->input('accuracy'));

        return $accuracy === null || $accuracy === '' ? null : (float) $accuracy;
    }

    private function officeDistance(Request $request): array
    {
        $officeLat = (float) Setting::getValue('office_latitude', '0');
        $officeLng = (float) Setting::getValue('office_longitude', '0');
        $radius = (int) Setting::getValue('office_radius_meters', '100');

        return [
            'distance' => $this->haversineDistance(
                $request->latitude,
                $request->longitude,
                $officeLat,
                $officeLng
            ),
            'radius' => $radius,
        ];
    }

    private function locationAuditAttributes(Request $request, string $prefix): array
    {
        return [
            "{$prefix}_accuracy_meters" => $this->locationAccuracy($request),
            "{$prefix}_is_mocked" => $this->locationIsMocked($request),
            "{$prefix}_location_recorded_at" => $request->location_timestamp
                ? Carbon::parse($request->location_timestamp)
                : null,
        ];
    }

    private function locationReviewAttributes(
        Request $request,
        string $prefix,
        bool $requireGps,
        ?Attendance $attendance = null
    ): array {
        $securityFlags = is_array($attendance?->security_flags) ? $attendance->security_flags : [];
        $issue = $this->locationReviewIssue($request, $prefix, $requireGps);

        $securityFlags[$prefix] = [
            'is_mocked' => $this->locationIsMocked($request),
            'accuracy_meters' => $this->locationAccuracy($request),
            'max_accuracy_meters' => (float) Setting::getValue('max_gps_accuracy_meters', '100'),
            'recorded_at' => $request->location_timestamp,
            'issue' => $issue,
        ];

        $attributes = ['security_flags' => $securityFlags];

        if ($issue) {
            return $attributes + [
                'review_status' => 'pending',
                'suspicious_reason' => $this->mergeSuspiciousReason($attendance?->suspicious_reason, $issue),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ];
        }

        if (!$attendance) {
            $attributes['review_status'] = null;
            $attributes['suspicious_reason'] = null;
        }

        return $attributes;
    }

    private function locationReviewIssue(Request $request, string $prefix, bool $requireGps): ?string
    {
        if (!$requireGps) {
            return null;
        }

        $label = $prefix === 'clock_out' ? 'clock out' : 'clock in';
        $issues = [];

        if ($this->locationIsMocked($request)) {
            $issues[] = 'Fake GPS terdeteksi saat '.$label;
        }

        $accuracy = $this->locationAccuracy($request);
        $maxAccuracy = (float) Setting::getValue('max_gps_accuracy_meters', '100');

        if ($accuracy !== null && $maxAccuracy > 0 && $accuracy > $maxAccuracy) {
            $issues[] = 'Akurasi GPS rendah saat '.$label.' ('.round($accuracy).'m, maksimal '.round($maxAccuracy).'m)';
        }

        return $issues ? implode('; ', $issues) : null;
    }

    private function mergeSuspiciousReason(?string $existingReason, string $newReason): string
    {
        if (!$existingReason) {
            return $newReason;
        }

        return str_contains($existingReason, $newReason)
            ? $existingReason
            : $existingReason.'; '.$newReason;
    }

    private function notifyAttendanceSecurityReview(Attendance $attendance, string $event): void
    {
        $attendance->loadMissing('employee:id,company_id,full_name');
        $employee = $attendance->employee;

        if (!$employee || $attendance->review_status !== 'pending') {
            return;
        }

        $permission = app(AdminPermission::class);
        $recipients = Employee::where('company_id', $employee->company_id)
            ->where('id', '!=', $employee->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Employee $recipient) => $this->shouldReceiveAttendanceSecurityNotification($recipient, $permission));

        if ($recipients->isEmpty()) {
            return;
        }

        $message = $this->attendanceSecurityReviewMessage($attendance, $event);

        foreach ($recipients as $recipient) {
            $notification = Notification::firstOrNew([
                'employee_id' => $recipient->id,
                'type' => 'attendance_security_review',
                'reference_type' => Attendance::class,
                'reference_id' => $attendance->id,
            ]);

            $notification->fill([
                'title' => 'Presensi Mencurigakan',
                'message' => $message,
                'is_read' => false,
            ]);
            $notification->save();

            $this->sendAttendanceSecurityPush($recipient, $notification);
        }
    }

    private function notifyHrAdminsOfClockIn(Attendance $attendance): void
    {
        $attendance->loadMissing('employee:id,company_id,full_name');
        $employee = $attendance->employee;

        if (!$employee || !$attendance->clock_in) {
            return;
        }

        $permission = app(AdminPermission::class);
        $recipients = Employee::where('company_id', $employee->company_id)
            ->where('id', '!=', $employee->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Employee $recipient) => $this->shouldReceiveHrClockInNotification($recipient, $permission));

        if ($recipients->isEmpty()) {
            return;
        }

        $clockIn = substr((string) $attendance->clock_in, 0, 5);
        $date = $attendance->date ? Carbon::parse($attendance->date)->format('d/m/Y') : '-';
        $attendanceDate = $attendance->date ? Carbon::parse($attendance->date)->toDateString() : null;
        $sameDayAttendanceIds = Attendance::where('employee_id', $employee->id)
            ->when(
                $attendanceDate,
                fn ($query) => $query->whereDate('date', $attendanceDate),
                fn ($query) => $query->whereKey($attendance->id)
            )
            ->pluck('id');
        $message = "{$employee->full_name} sudah clock-in pukul {$clockIn} pada {$date}.";

        foreach ($recipients as $recipient) {
            $notification = Notification::where('employee_id', $recipient->id)
                ->where('type', 'attendance_clock_in')
                ->where('reference_type', Attendance::class)
                ->whereIn('reference_id', $sameDayAttendanceIds)
                ->first();

            if (!$notification) {
                $notification = new Notification([
                    'employee_id' => $recipient->id,
                    'type' => 'attendance_clock_in',
                    'reference_type' => Attendance::class,
                    'reference_id' => $attendance->id,
                ]);
            }

            $notification->fill([
                'title' => 'Clock-In Karyawan',
                'message' => $message,
                'is_read' => false,
            ]);
            $notification->save();

            $this->sendHrClockInPush($recipient, $notification);
        }
    }

    private function shouldReceiveHrClockInNotification(Employee $recipient, AdminPermission $permission): bool
    {
        return in_array('hr_admin', $permission->roleSlugs($recipient), true);
    }

    protected function sendHrClockInPush(Employee $recipient, Notification $notification): void
    {
        FcmService::sendToEmployee($recipient, $notification->title, $notification->message, [
            'type' => 'attendance_clock_in',
            'reference_type' => 'attendance',
            'reference_id' => (string) $notification->reference_id,
        ]);
    }

    private function shouldReceiveAttendanceSecurityNotification(Employee $recipient, AdminPermission $permission): bool
    {
        return (bool) array_intersect($permission->roleSlugs($recipient), ['superadmin', 'hr_admin']);
    }

    protected function sendAttendanceSecurityPush(Employee $recipient, Notification $notification): void
    {
        FcmService::sendToEmployee($recipient, $notification->title, $notification->message, [
            'type' => 'attendance_security_review',
            'reference_type' => 'attendance',
            'reference_id' => (string) $notification->reference_id,
        ]);
    }

    private function attendanceSecurityReviewMessage(Attendance $attendance, string $event): string
    {
        $eventLabel = $event === 'clock_out' ? 'clock out' : 'clock in';
        $reason = $attendance->suspicious_reason ?: 'presensi mencurigakan';
        $summary = str_replace(
            [
                'Fake GPS terdeteksi saat '.$eventLabel,
                'Akurasi GPS rendah saat '.$eventLabel,
            ],
            [
                'Fake GPS saat '.$eventLabel,
                'akurasi GPS rendah saat '.$eventLabel,
            ],
            $reason
        );

        $date = $attendance->date ? Carbon::parse($attendance->date)->format('d/m/Y') : '-';

        return "{$attendance->employee->full_name} terdeteksi {$summary}. Periksa presensi tanggal {$date}.";
    }

    public function clockOut(Request $request)
    {
        $requirePhoto = Setting::getValue('require_photo', '1') === '1';
        $requireGps = Setting::getValue('require_gps', '1') === '1';

        $request->validate([
            'latitude' => $requireGps ? 'required|numeric' : 'nullable|numeric',
            'longitude' => $requireGps ? 'required|numeric' : 'nullable|numeric',
            'location_accuracy' => 'nullable|numeric|min:0',
            'accuracy' => 'nullable|numeric|min:0',
            'is_mock_location' => 'nullable|boolean',
            'is_mocked' => 'nullable|boolean',
            'location_is_mocked' => 'nullable|boolean',
            'location_timestamp' => 'nullable|date',
            'photo' => 'nullable|image|max:5120',
            'photo_base64' => 'nullable|string',
        ]);

        if ($requirePhoto && !$request->hasFile('photo') && !$request->photo_base64) {
            return response()->json([
                'success' => false,
                'message' => 'Foto selfie wajib diambil.',
            ], 422);
        }

        $employee = $request->user();

        // Cari attendance yang belum clock out — support overnight shift (mis. 18:00–06:00)
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->latest('date')
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum clock in atau sudah clock out.',
            ], 422);
        }

        if ($requireGps && $request->latitude && $request->longitude) {
            ['distance' => $distance, 'radius' => $radius] = $this->officeDistance($request);

            if ($distance > $radius && Setting::getValue('allow_remote_clockin', '0') !== '1') {
                return response()->json([
                    'success' => false,
                    'message' => "Anda berada di luar radius kantor ({$radius}m). Clock-out remote tidak diizinkan.",
                    'distance' => round($distance),
                    'radius' => $radius,
                ], 422);
            }
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('attendance/clock-out', 'public');
        } elseif ($request->photo_base64) {
            $photoPath = $this->storeBase64Photo($request->photo_base64, 'attendance/clock-out');
        }

        // Face Verification saat clock-out
        $faceVerify = Setting::getValue('face_verification_enabled', '1') === '1';
        if ($faceVerify && $employee->face_photo && $photoPath) {
            $result = (new FaceVerificationService())->verify($photoPath, $employee->face_photo);

            if (!$result['match']) {
                Storage::disk('public')->delete($photoPath);
                return response()->json([
                    'success'    => false,
                    'message'    => $result['message'],
                    'similarity' => isset($result['similarity']) ? round($result['similarity'] * 100, 1) : null,
                ], 422);
            }
        }

        $clockOutTime = now();

        // Jika ada izin pulang cepat yang sudah di-ACC untuk tanggal absensi ini,
        // simpan sebagai status izin (hanya bila belum punya status izin lain).
        $clockOutStatus = ($attendance->status === 'present'
            && AttendanceLeaveSync::approvedTargetForDate($employee, $attendance->date) === AttendanceLateExcuse::EARLY_DEPARTURE_STATUS)
            ? ['status' => AttendanceLateExcuse::EARLY_DEPARTURE_STATUS]
            : [];

        $attendance->update([
            'clock_out' => $clockOutTime->format('H:i:s'),
            'clock_out_lat' => $request->latitude,
            'clock_out_lng' => $request->longitude,
            ...$this->locationAuditAttributes($request, 'clock_out'),
            ...$this->locationReviewAttributes($request, 'clock_out', $requireGps, $attendance),
            'clock_out_photo' => $photoPath,
            ...$clockOutStatus,
        ]);
        $attendance->refresh();
        $needsReview = $attendance->review_status === 'pending';
        if ($needsReview) {
            $this->notifyAttendanceSecurityReview($attendance, 'clock_out');
        }

        // === OVERTIME ACTUAL CALCULATION ===
        $attendanceDate = Carbon::parse($attendance->date);
        $overtimeInfo = null;
        $overtimeRequest = OvertimeRequest::where('employee_id', $employee->id)
            ->where('date', $attendanceDate->format('Y-m-d'))
            ->where('status', 'approved')
            ->first();

        if ($overtimeRequest) {
            $actualOt = $this->calculateActualOvertime(
                $overtimeRequest, $employee, $attendanceDate, $attendance, $clockOutTime
            );

            $overtimeRequest->update([
                'actual_duration' => $actualOt,
                'actual_clock_in' => $attendance->clock_in,
                'actual_clock_out' => $clockOutTime->format('H:i:s'),
            ]);

            $overtimeInfo = [
                'overtime_type' => $overtimeRequest->overtime_type,
                'requested_duration' => $overtimeRequest->total_duration,
                'break_duration' => $overtimeRequest->approved_break ?? $overtimeRequest->break_duration,
                'actual_duration' => $actualOt,
                'actual_formatted' => $overtimeRequest->fresh()->actual_duration_formatted,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Clock out berhasil' . ($overtimeInfo ? ' (Lembur: ' . $overtimeInfo['actual_formatted'] . ')' : ''),
            'data' => $attendance,
            'overtime' => $overtimeInfo,
            'needs_review' => $needsReview,
            'review_status' => $attendance->review_status,
        ]);
    }

    /**
     * Get attendance settings for mobile app.
     */
    public function settings()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'office_latitude' => (float) Setting::getValue('office_latitude', '0'),
                'office_longitude' => (float) Setting::getValue('office_longitude', '0'),
                'office_radius_meters' => (int) Setting::getValue('office_radius_meters', '100'),
                'office_address' => Setting::getValue('office_address', ''),
                'require_photo' => Setting::getValue('require_photo', '1') === '1',
                'require_gps' => Setting::getValue('require_gps', '1') === '1',
                'allow_remote_clockin' => Setting::getValue('allow_remote_clockin', '0') === '1',
                'remote_requires_approval' => Setting::getValue('remote_requires_approval', '1') === '1',
                'remote_requires_notes' => Setting::getValue('remote_requires_notes', '1') === '1',
            ],
        ]);
    }

    /**
     * Decode base64 photo and store to disk.
     */
    private function storeBase64Photo(string $base64, string $directory): string
    {
        $imageData = base64_decode($base64);
        $fileName = $directory . '/' . uniqid() . '.jpg';
        Storage::disk('public')->put($fileName, $imageData);
        return $fileName;
    }

    /**
     * Calculate actual overtime based on clock out time.
     * Applies 15-minute rounding (ceil).
     */
    private function calculateActualOvertime(
        OvertimeRequest $otRequest,
        Employee $employee,
        Carbon $today,
        Attendance $attendance,
        \Carbon\CarbonInterface $clockOutTime
    ): int {
        $duration = $otRequest->approved_duration ?? $otRequest->total_duration;
        $breakMin = $otRequest->approved_break ?? $otRequest->break_duration ?? 0;
        $actualOt = 0;

        if ($otRequest->overtime_type === 'holiday') {
            // === HARI LIBUR/OFF: hitung dari planned_start/end vs clock in/out ===
            $plannedStart = Carbon::parse($today->format('Y-m-d') . ' ' . $otRequest->planned_start);
            $plannedEnd = Carbon::parse($today->format('Y-m-d') . ' ' . $otRequest->planned_end);
            $actualStart = Carbon::parse($today->format('Y-m-d') . ' ' . $attendance->clock_in);

            // Effective start = whichever is later (clock_in or planned_start)
            $effectiveStart = $actualStart->greaterThan($plannedStart) ? $actualStart : $plannedStart;

            // Effective end = whichever is earlier (clock_out or planned_end)
            $effectiveEnd = $clockOutTime->lessThan($plannedEnd) ? $clockOutTime : $plannedEnd;

            $workMinutes = max(0, (int) $effectiveStart->diffInMinutes($effectiveEnd));

            // Subtract break, cap to max approved
            $maxOt = max(0, $duration - $breakMin);
            $actualOt = min(max(0, $workMinutes - $breakMin), $maxOt);
        } else {
            // === HARI KERJA: pre-shift + post-shift ===

            // -- Post-shift overtime --
            if (($otRequest->post_shift_duration ?? 0) > 0 || $otRequest->total_duration > 0) {
                $shiftEndTime = $this->getShiftEndTime($employee, $today);

                if ($shiftEndTime) {
                    $otRequest->update(['shift_end_time' => $shiftEndTime]);
                    $shiftEnd = Carbon::parse($today->format('Y-m-d') . ' ' . $shiftEndTime);

                    // Minutes worked after shift end
                    $postShiftMinutes = max(0, (int) $clockOutTime->floatDiffInMinutes($shiftEnd, false));

                    $postBreak = $otRequest->approved_break
                        ?? $otRequest->post_shift_break ?? 0;
                    $postOt = max(0, $postShiftMinutes - $postBreak);

                    // Cap to approved/requested post-shift duration minus break
                    $approvedPost = $otRequest->approved_duration
                        ?? $otRequest->post_shift_duration
                        ?? $otRequest->total_duration ?? 0;
                    $maxPostOt = max(0, $approvedPost - $postBreak);
                    $postOt = min($postOt, $maxPostOt);

                    $actualOt += $postOt;
                }
            }

            // -- Pre-shift overtime --
            if (($otRequest->pre_shift_duration ?? 0) > 0 && $attendance->clock_in) {
                $shiftStartTime = $this->getShiftStartTime($employee, $today);

                if ($shiftStartTime) {
                    $shiftStart = Carbon::parse($today->format('Y-m-d') . ' ' . $shiftStartTime);
                    $clockIn = Carbon::parse($today->format('Y-m-d') . ' ' . $attendance->clock_in);

                    // Minutes worked before shift start
                    $preShiftMinutes = max(0, (int) $shiftStart->floatDiffInMinutes($clockIn, false));

                    $preBreak = $otRequest->pre_shift_break ?? 0;
                    $preOt = max(0, $preShiftMinutes - $preBreak);

                    // Cap to requested pre-shift duration minus break
                    $maxPreOt = max(0, $otRequest->pre_shift_duration - $preBreak);
                    $preOt = min($preOt, $maxPreOt);

                    $actualOt += $preOt;
                }
            }
        }

        // Round up to nearest 15 minutes
        $actualOt = (int) (ceil($actualOt / 15) * 15);

        return $actualOt;
    }

    /**
     * Get shift end time for an employee on a specific date.
     */
    private function getShiftEndTime(Employee $employee, Carbon $date): ?string
    {
        // 1. Check override in schedule_assignments
        $override = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        if ($override?->shift && !$override->shift->is_off) {
            return $override->shift->end_time;
        }

        // 2. Fallback ke template yang berlaku pada tanggal itu (riwayat).
        $shift = $employee->scheduleTemplateOn($date)?->getShiftForDay($date->dayOfWeekIso);
        if ($shift && !$shift->is_off) {
            return $shift->end_time;
        }

        // 3. Fallback to work schedule
        if ($employee->work_schedule_id) {
            $employee->loadMissing('workSchedule');
            return $employee->workSchedule?->end_time;
        }

        return null;
    }

    /**
     * Get shift start time for an employee on a specific date.
     */
    private function getShiftStartTime(Employee $employee, Carbon $date): ?string
    {
        // Delegasi ke helper bersama agar hitung-ulang saat ganti shift memakai
        // logika resolusi shift yang identik dengan saat clock-in.
        return \App\Support\AttendanceLate::shiftStartTime($employee, $date);
    }
}

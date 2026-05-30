<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\ScheduleAssignment;
use App\Models\Setting;
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

        $hadir = (clone $query)->where('status', 'present')->count();
        $absen = (clone $query)->where('status', 'absent')->count();
        $terlambat = (clone $query)->where('is_late', true)->count();

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

        $startOfMonth = $period->copy()->startOfMonth();
        $endOfMonth = $period->copy()->endOfMonth();
        $daysInMonth = $period->daysInMonth;

        // Load template days (keyed by day_of_week: 1=Mon..7=Sun)
        $templateDays = [];
        if ($employee->schedule_template_id) {
            $employee->load('scheduleTemplate.days.shift');
            if ($employee->scheduleTemplate) {
                foreach ($employee->scheduleTemplate->days as $day) {
                    $templateDays[$day->day_of_week] = $day->shift;
                }
            }
        }

        // Load overrides for month
        $overrides = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($o) => $o->date->format('Y-m-d'));

        // Load attendances for month
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($a) => $a->date->format('Y-m-d'));

        // Load holidays
        $holidays = Holiday::where('company_id', $employee->company_id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($h) => $h->date->format('Y-m-d'));

        // Load approved leaves for month
        $leaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $endOfMonth->format('Y-m-d'))
            ->where('end_date', '>=', $startOfMonth->format('Y-m-d'))
            ->get();

        $dayNames = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        $days = [];
        $stats = ['hadir' => 0, 'terlambat' => 0, 'alpha' => 0, 'cuti' => 0, 'off' => 0, 'libur' => 0];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $startOfMonth->copy()->addDays($d - 1);
            $dateStr = $date->format('Y-m-d');
            $dow = $date->dayOfWeekIso; // 1=Mon..7=Sun

            // Holiday check
            $holiday = $holidays[$dateStr] ?? null;

            // Leave check
            $leave = $leaves->first(function ($l) use ($date) {
                return $date->between($l->start_date, $l->end_date);
            });

            // Determine shift: only when NOT holiday and NOT on leave
            $shift = null;
            if (!$holiday && !$leave) {
                if (isset($overrides[$dateStr])) {
                    $shift = $overrides[$dateStr]->shift;
                } elseif (isset($templateDays[$dow])) {
                    $shift = $templateDays[$dow];
                }
            }

            // Attendance
            $att = $attendances[$dateStr] ?? null;

            // Calculate stats
            if ($holiday) {
                $stats['libur']++;
            } elseif ($leave) {
                $stats['cuti']++;
            } elseif ($shift && $shift->is_off) {
                $stats['off']++;
            } elseif ($att) {
                if ($att->is_late) {
                    $stats['terlambat']++;
                }
                $stats['hadir']++;
            } elseif ($shift && !$shift->is_off && $date->lte(Carbon::today())) {
                $stats['alpha']++;
            }

            $days[] = [
                'date' => $dateStr,
                'day' => $d,
                'day_name' => $dayNames[$dow],
                'is_today' => $date->isToday(),
                'holiday' => $holiday ? $holiday->name : null,
                'leave' => $leave ? [
                    'type' => $leave->leaveType->name ?? 'Cuti',
                ] : null,
                'shift' => $shift ? [
                    'name' => $shift->name,
                    'start_time' => $shift->start_time ? substr($shift->start_time, 0, 5) : null,
                    'end_time' => $shift->end_time ? substr($shift->end_time, 0, 5) : null,
                    'color' => $shift->color,
                    'is_off' => $shift->is_off,
                ] : null,
                'attendance' => $att ? [
                    'id' => $att->id,
                    'clock_in' => $att->clock_in,
                    'clock_out' => $att->clock_out,
                    'clock_in_photo' => $att->clock_in_photo,
                    'clock_out_photo' => $att->clock_out_photo,
                    'clock_in_lat' => $att->clock_in_lat,
                    'clock_in_lng' => $att->clock_in_lng,
                    'clock_out_lat' => $att->clock_out_lat,
                    'clock_out_lng' => $att->clock_out_lng,
                    'status' => $att->status,
                    'is_late' => $att->is_late,
                    'is_remote' => $att->is_remote,
                    'remote_notes' => $att->remote_notes,
                ] : null,
            ];
        }

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

        // Check if already clocked in
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
            $officeLat = (float) Setting::getValue('office_latitude', '0');
            $officeLng = (float) Setting::getValue('office_longitude', '0');
            $radius = (int) Setting::getValue('office_radius_meters', '100');

            $distance = $this->haversineDistance(
                $request->latitude, $request->longitude,
                $officeLat, $officeLng
            );

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
            // Wajib daftar foto wajah terlebih dahulu
            if (!$employee->face_photo) {
                if ($photoPath) Storage::disk('public')->delete($photoPath);
                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum mendaftarkan foto verifikasi wajah. Silakan daftarkan di menu Akun → Verifikasi Wajah terlebih dahulu.',
                ], 422);
            }

            if ($photoPath) {
                $selfieFullPath = Storage::disk('public')->path($photoPath);
                $faceFullPath   = Storage::disk('public')->path($employee->face_photo);

                if (file_exists($selfieFullPath) && file_exists($faceFullPath)) {
                    $similarity = $this->compareFaces($selfieFullPath, $faceFullPath);

                    if ($similarity < 0.50) {
                        Storage::disk('public')->delete($photoPath);
                        return response()->json([
                            'success'    => false,
                            'message'    => 'Verifikasi wajah gagal. Wajah tidak sesuai dengan foto verifikasi wajah Anda.',
                            'similarity' => round($similarity * 100, 1),
                        ], 422);
                    }
                }
            }
        }

        // Check if late
        $isLate = false;
        $shiftStartTime = $this->getShiftStartTime($employee, $today);
        if ($shiftStartTime) {
            $scheduleStart = Carbon::parse($today->toDateString() . ' ' . $shiftStartTime);
            $isLate = now()->gt($scheduleStart);
        }

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today->toDateString()],
            [
                'clock_in' => now()->format('H:i:s'),
                'clock_in_lat' => $request->latitude,
                'clock_in_lng' => $request->longitude,
                'clock_in_photo' => $photoPath,
                'status' => 'present',
                'is_late' => $isLate,
                'is_remote' => $isRemote,
                'remote_notes' => $isRemote ? $request->notes : null,
            ]
        );

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

    public function clockOut(Request $request)
    {
        $requirePhoto = Setting::getValue('require_photo', '1') === '1';
        $requireGps = Setting::getValue('require_gps', '1') === '1';

        $request->validate([
            'latitude' => $requireGps ? 'required|numeric' : 'nullable|numeric',
            'longitude' => $requireGps ? 'required|numeric' : 'nullable|numeric',
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

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('attendance/clock-out', 'public');
        } elseif ($request->photo_base64) {
            $photoPath = $this->storeBase64Photo($request->photo_base64, 'attendance/clock-out');
        }

        $clockOutTime = now();

        $attendance->update([
            'clock_out' => $clockOutTime->format('H:i:s'),
            'clock_out_lat' => $request->latitude,
            'clock_out_lng' => $request->longitude,
            'clock_out_photo' => $photoPath,
        ]);

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
     * Basic face verification using color histogram comparison.
     * Returns similarity score 0.0 to 1.0
     */
    private function compareFaces(string $path1, string $path2): float
    {
        try {
            $img1 = $this->loadImage($path1);
            $img2 = $this->loadImage($path2);

            if (!$img1 || !$img2) return 0.0;

            // Resize both to 64x64 for uniform comparison
            $size = 64;
            $thumb1 = imagecreatetruecolor($size, $size);
            $thumb2 = imagecreatetruecolor($size, $size);

            imagecopyresampled($thumb1, $img1, 0, 0, 0, 0, $size, $size, imagesx($img1), imagesy($img1));
            imagecopyresampled($thumb2, $img2, 0, 0, 0, 0, $size, $size, imagesx($img2), imagesy($img2));

            imagedestroy($img1);
            imagedestroy($img2);

            // Build color histograms (16 bins per channel = 48 total bins)
            $bins = 16;
            $hist1 = array_fill(0, $bins * 3, 0);
            $hist2 = array_fill(0, $bins * 3, 0);

            for ($x = 0; $x < $size; $x++) {
                for ($y = 0; $y < $size; $y++) {
                    $rgb1 = imagecolorat($thumb1, $x, $y);
                    $rgb2 = imagecolorat($thumb2, $x, $y);

                    $r1 = ($rgb1 >> 16) & 0xFF;
                    $g1 = ($rgb1 >> 8) & 0xFF;
                    $b1 = $rgb1 & 0xFF;

                    $r2 = ($rgb2 >> 16) & 0xFF;
                    $g2 = ($rgb2 >> 8) & 0xFF;
                    $b2 = $rgb2 & 0xFF;

                    $hist1[intdiv($r1, (256 / $bins))]++;
                    $hist1[$bins + intdiv($g1, (256 / $bins))]++;
                    $hist1[$bins * 2 + intdiv($b1, (256 / $bins))]++;

                    $hist2[intdiv($r2, (256 / $bins))]++;
                    $hist2[$bins + intdiv($g2, (256 / $bins))]++;
                    $hist2[$bins * 2 + intdiv($b2, (256 / $bins))]++;
                }
            }

            imagedestroy($thumb1);
            imagedestroy($thumb2);

            // Cosine similarity
            $dot = 0;
            $mag1 = 0;
            $mag2 = 0;
            for ($i = 0; $i < count($hist1); $i++) {
                $dot += $hist1[$i] * $hist2[$i];
                $mag1 += $hist1[$i] * $hist1[$i];
                $mag2 += $hist2[$i] * $hist2[$i];
            }

            $mag1 = sqrt($mag1);
            $mag2 = sqrt($mag2);

            if ($mag1 == 0 || $mag2 == 0) return 0.0;

            return $dot / ($mag1 * $mag2);
        } catch (\Throwable $e) {
            // If comparison fails, allow clock-in (don't block on error)
            \Log::warning('Face comparison error: ' . $e->getMessage());
            return 1.0;
        }
    }

    /**
     * Load image from file path regardless of format.
     */
    private function loadImage(string $path): \GdImage|false
    {
        $info = @getimagesize($path);
        if (!$info) return false;

        return match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };
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

            $workMinutes = max(0, $effectiveEnd->diffInMinutes($effectiveStart, false));

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

        // 2. Fallback to schedule template
        if ($employee->schedule_template_id) {
            $employee->loadMissing('scheduleTemplate.days.shift');
            $shift = $employee->scheduleTemplate?->getShiftForDay($date->dayOfWeekIso);
            if ($shift && !$shift->is_off) {
                return $shift->end_time;
            }
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
        // 1. Check override in schedule_assignments
        $override = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        if ($override?->shift && !$override->shift->is_off) {
            return $override->shift->start_time;
        }

        // 2. Fallback to schedule template
        if ($employee->schedule_template_id) {
            $employee->loadMissing('scheduleTemplate.days.shift');
            $shift = $employee->scheduleTemplate?->getShiftForDay($date->dayOfWeekIso);
            if ($shift && !$shift->is_off) {
                return $shift->start_time;
            }
        }

        // 3. Fallback to work schedule
        if ($employee->work_schedule_id) {
            $employee->loadMissing('workSchedule');
            return $employee->workSchedule?->start_time;
        }

        return null;
    }
}


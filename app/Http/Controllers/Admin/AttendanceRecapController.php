<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\ScheduleAssignment;
use App\Support\AttendanceLateExcuse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

class AttendanceRecapController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
        $departmentId = $request->department_id;
        $search = $request->search;
        $filterStatus = $request->status;

        // Check if holiday
        $holiday = Holiday::where('company_id', $admin->company_id)
            ->where('date', $date->format('Y-m-d'))
            ->first();

        // Load employees with their templates
        $query = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->with(['department:id,name', 'scheduleTemplate.days.shift']);

        // Manager hanya melihat rekap departemennya sendiri.
        if ($managerDept = \App\Support\AdminDataScope::departmentId($admin)) {
            $query->where('department_id', $managerDept);
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        if ($search) {
            $query->where('full_name', 'like', "%{$search}%");
        }

        $employees = $query->orderBy('department_id')->orderBy('full_name')->get();

        // Load attendances for this date
        $dateString = $date->format('Y-m-d');

        $attendances = Attendance::whereDate('date', $dateString)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        // Load approved leaves covering this date
        $leaves = LeaveRequest::with('leaveType')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('status', 'approved')
            ->where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->get()
            ->keyBy('employee_id');

        // Load manual schedule overrides for this date
        $overrides = ScheduleAssignment::with('shift')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('date', $date->format('Y-m-d'))
            ->get()
            ->keyBy('employee_id');

        // Build recap rows
        $rows = [];
        $stats = ['hadir' => 0, 'terlambat' => 0, 'wfh' => 0, 'sakit' => 0, 'cuti' => 0, 'alpha' => 0, 'off' => 0, 'libur' => 0];

        foreach ($employees as $emp) {
            $row = [
                'employee' => $emp,
                'shift' => null,
                'attendance' => null,
                'leave' => null,
                'late_excuse' => null,
                'status' => 'no_schedule', // no_schedule, off, holiday, leave, present, late, absent
                'status_label' => '-',
                'clock_in' => null,
                'clock_out' => null,
            ];

            // Shift: manual override wins over holiday; template only applies on non-holidays.
            $override = $overrides->get($emp->id);
            if ($override) {
                $row['shift'] = $override->shift;
            } elseif (!$holiday && $emp->scheduleTemplate) {
                $row['shift'] = $emp->scheduleTemplate->getShiftForDay($date->dayOfWeekIso);
            }

            $att = $attendances->get($emp->id);
            if ($att) {
                $row['attendance'] = $att;
                $row['clock_in'] = $att->clock_in;
                $row['clock_out'] = $att->clock_out;
            }

            // Determine status for this day.
            if ($holiday && !$row['shift']) {
                $row['status'] = 'holiday';
                $row['status_label'] = 'Libur Nasional';
                $stats['libur']++;
            } else {
                $shift = $row['shift'];

                if (!$shift) {
                    $row['status'] = 'no_schedule';
                    $row['status_label'] = 'Tidak Ada Jadwal';
                } elseif ($shift->is_off) {
                    $row['status'] = 'off';
                    $row['status_label'] = 'Off / Libur';
                    $stats['off']++;
                } else {
                    // Has schedule — check leave first
                    $leave = $leaves->get($emp->id);
                    $lateExcuse = AttendanceLateExcuse::isLateArrivalLeave($leave) ? $leave : null;
                    $earlyDeparture = AttendanceLateExcuse::isEarlyDepartureLeave($leave) ? $leave : null;
                    $partialDayLeave = $lateExcuse || $earlyDeparture;
                    if ($leave) {
                        $row['leave'] = $leave;
                    }
                    if ($lateExcuse) {
                        $row['late_excuse'] = $lateExcuse;
                    }

                    if ($leave && !$partialDayLeave) {
                        if ($this->isSickLeave($leave)) {
                            $row['status'] = 'sick';
                            $row['status_label'] = 'Sakit';
                            $stats['sakit']++;
                        } elseif (AttendanceLateExcuse::isWfhLeave($leave)) {
                            $row['status'] = 'wfh';
                            $row['status_label'] = 'WFH';
                            $stats['wfh']++;
                        } else {
                            $row['status'] = 'leave';
                            $row['status_label'] = 'Cuti: ' . ($leave->leaveType->name ?? 'Cuti');
                            $stats['cuti']++;
                        }
                    } else {
                        // Check attendance
                        $att = $row['attendance'];
                        if ($att) {
                            if ($att->review_status === 'pending') {
                                $row['status'] = 'review';
                                $row['status_label'] = 'Butuh Review';
                            } elseif ($att->status === 'sick') {
                                $row['status'] = 'sick';
                                $row['status_label'] = 'Sakit';
                                $stats['sakit']++;
                            } elseif ($att->status === 'wfh') {
                                $row['status'] = 'wfh';
                                $row['status_label'] = 'WFH';
                                $stats['wfh']++;
                            } elseif ($att->review_status === 'rejected' || $att->status === 'absent') {
                                $row['status'] = 'absent';
                                $row['status_label'] = 'Alpha';
                                $stats['alpha']++;
                            } elseif ($manualPermissionLabel = AttendanceLateExcuse::manualPermissionStatusLabel($att->status)) {
                                $row['status'] = 'present';
                                $row['status_label'] = $manualPermissionLabel;
                                $stats['hadir']++;
                            } elseif ($att->is_late && !$lateExcuse) {
                                $row['status'] = 'late';
                                $row['status_label'] = 'Terlambat';
                                $stats['terlambat']++;
                            } else {
                                $row['status'] = 'present';
                                $row['status_label'] = $att->is_late && $lateExcuse
                                    ? AttendanceLateExcuse::STATUS_LABEL
                                    : ($earlyDeparture ? AttendanceLateExcuse::EARLY_DEPARTURE_STATUS_LABEL : 'Hadir');
                                $stats['hadir']++;
                            }
                        } else {
                            // No attendance, is it future or past?
                            if ($date->isFuture()) {
                                $row['status'] = 'scheduled';
                                $row['status_label'] = 'Terjadwal';
                            } else {
                                $row['status'] = 'absent';
                                $row['status_label'] = 'Alpha';
                                $stats['alpha']++;
                            }
                        }
                    }
                }
            }

            // Status filter
            if ($filterStatus && $row['status'] !== $filterStatus) {
                continue;
            }

            $rows[] = $row;
        }

        $departments = Department::where('company_id', $admin->company_id)->orderBy('name')->get();

        return view('admin.attendance-recap.index', compact(
            'rows', 'date', 'holiday', 'stats', 'departments',
            'departmentId', 'search', 'filterStatus'
        ));
    }

    public function import(Request $request)
    {
        $request->validate([
            'attendance_file' => 'required|file|mimes:csv,txt,xlsx|max:5120',
        ]);

        $admin = Employee::find(session('admin_id'));
        $rows = $this->readImportRows($request->file('attendance_file'));
        $headers = array_map(fn ($header) => $this->normalizeImportHeader($header), array_shift($rows) ?? []);
        $imported = 0;
        $skipped = 0;
        $warnings = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $data = $this->combineImportRow($headers, $row);

            if ($this->isBlankImportRow($data)) {
                continue;
            }

            $employeeCode = trim((string) ($data['employee_code'] ?? $data['employee_id'] ?? $data['kode_karyawan'] ?? $data['kode'] ?? ''));
            $dateValue = $data['date'] ?? $data['tanggal'] ?? null;
            $clockIn = $this->normalizeImportTime($data['clock_in'] ?? $data['check_in'] ?? $data['jam_masuk'] ?? null);
            $clockOut = $this->normalizeImportTime($data['clock_out'] ?? $data['check_out'] ?? $data['jam_keluar'] ?? null);
            $overtimeClockIn = $this->normalizeImportTime($data['overtime_check_in'] ?? $data['ot_check_in'] ?? $data['lembur_masuk'] ?? null);
            $overtimeClockOut = $this->normalizeImportTime($data['overtime_check_out'] ?? $data['ot_check_out'] ?? $data['lembur_keluar'] ?? null);
            $overtimeBreak = $this->normalizeImportDurationMinutes($data['overtime_break'] ?? $data['overtime_break_duration'] ?? $data['break_duration'] ?? $data['istirahat_lembur'] ?? null);
            $date = $this->normalizeImportDate($dateValue);

            if ($employeeCode === '') {
                $skipped++;
                $warnings[] = "Baris {$lineNumber}: kode karyawan kosong.";
                continue;
            }

            $employee = Employee::where('company_id', $admin->company_id)
                ->where('employee_code', $employeeCode)
                ->first();

            if (! $employee) {
                $skipped++;
                $warnings[] = "Baris {$lineNumber}: kode karyawan {$employeeCode} tidak ditemukan.";
                continue;
            }

            if (! $date) {
                $skipped++;
                $warnings[] = "Baris {$lineNumber}: tanggal tidak valid.";
                continue;
            }

            $hasAttendanceTime = $clockIn || $clockOut;
            $hasOvertimeTime = $overtimeClockIn || $overtimeClockOut;

            if (! $hasAttendanceTime && ! $hasOvertimeTime) {
                $skipped++;
                $warnings[] = "Baris {$lineNumber}: clock in/out kosong atau tidak valid.";
                continue;
            }

            $rowImported = false;

            if ($hasAttendanceTime) {
                Attendance::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date' => $date->format('Y-m-d'),
                    ],
                    [
                        'clock_in' => $clockIn,
                        'clock_out' => $clockOut,
                        'status' => 'present',
                        'review_status' => null,
                        'is_late' => $this->isLateForImportedAttendance($employee, $date, $clockIn, $data['shift'] ?? null),
                    ]
                );

                $rowImported = true;
            }

            if ($hasOvertimeTime) {
                if (! $overtimeClockIn || ! $overtimeClockOut) {
                    $warnings[] = "Baris {$lineNumber}: overtime check in/out tidak lengkap.";
                } elseif ($this->upsertImportedOvertime($employee, $date, $data, $overtimeClockIn, $overtimeClockOut, $overtimeBreak, $clockOut)) {
                    $rowImported = true;
                } else {
                    $warnings[] = "Baris {$lineNumber}: durasi overtime tidak valid.";
                }
            }

            if ($rowImported) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        $message = "Import presensi selesai: {$imported} berhasil, {$skipped} dilewati.";
        if ($warnings) {
            $message .= ' ' . implode(' ', array_slice($warnings, 0, 5));
        }

        return back()->with($imported > 0 ? 'success' : 'error', $message);
    }

    public function update(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:present,absent,sick,leave,holiday,late_excuse,early_departure,wfh',
        ]);

        $date = Carbon::parse($request->date);
        $dateString = $date->format('Y-m-d');
        $isLate = false;
        if ($request->status === 'present' && $request->clock_in) {
            $emp = Employee::with('scheduleTemplate.days.shift')->find($request->employee_id);
            $isLate = $this->isLateForImportedAttendance($emp, $date, $request->clock_in);
        }

        $attendance = Attendance::where('employee_id', $request->employee_id)
            ->whereDate('date', $dateString)
            ->first() ?? new Attendance([
                'employee_id' => $request->employee_id,
                'date' => $dateString,
            ]);

        $admin = Employee::find(session('admin_id'));

        $attendance->fill([
            'clock_in' => $request->clock_in,
            'clock_out' => $request->clock_out,
            'status' => $request->status,
            'is_late' => $isLate,
            // Status diubah manual oleh admin → bersihkan flag review keamanan agar
            // konsisten di semua tampilan (riwayat & rekap tidak lagi menandai Alpha).
            'review_status' => null,
            'suspicious_reason' => null,
            'reviewed_by' => $admin?->id,
            'reviewed_at' => now(),
        ]);
        $attendance->save();

        return back()->with('success', 'Data presensi berhasil diperbarui.');
    }

    private function readImportRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            return $this->readXlsxRows($file->getRealPath());
        }

        return $this->readCsvRows($file->getRealPath());
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($rows === [] && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function readXlsxRows(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->readXlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName($this->xlsxWorksheetPath($zip, ['ATTENDENCE', 'ATTENDANCE']));
        $zip->close();

        if (! $sheetXml) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $xmlRow) {
            $row = [];
            $maxColumnIndex = -1;
            foreach ($xmlRow->c as $cell) {
                $attributes = $cell->attributes();
                $cellRef = (string) ($attributes['r'] ?? '');
                $columnIndex = $this->xlsxColumnIndex($cellRef);
                $maxColumnIndex = max($maxColumnIndex, $columnIndex);
                $type = (string) ($attributes['t'] ?? '');
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }

                $row[$columnIndex] = $value;
            }

            if ($row !== []) {
                ksort($row);
                $denseRow = [];
                for ($index = 0; $index <= $maxColumnIndex; $index++) {
                    $denseRow[] = $row[$index] ?? '';
                }
                $rows[] = $denseRow;
            }
        }

        return $rows;
    }

    private function xlsxWorksheetPath(ZipArchive $zip, array $preferredSheets): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (! $workbookXml || ! $relsXml) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = simplexml_load_string($workbookXml);
        $relations = simplexml_load_string($relsXml);
        $relationTargets = [];

        foreach ($relations->Relationship ?? [] as $relation) {
            $attributes = $relation->attributes();
            $relationTargets[(string) $attributes['Id']] = (string) $attributes['Target'];
        }

        $fallbackPath = null;
        foreach ($workbook->sheets->sheet ?? [] as $sheet) {
            $attributes = $sheet->attributes();
            $relationAttributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationId = (string) ($relationAttributes['id'] ?? '');
            $target = $relationTargets[$relationId] ?? '';
            $path = $this->normalizeXlsxWorksheetPath($target);
            $sheetName = (string) ($attributes['name'] ?? '');

            $fallbackPath ??= $path;
            foreach ($preferredSheets as $preferredSheet) {
                if (strcasecmp($sheetName, $preferredSheet) === 0) {
                    return $path;
                }
            }
        }

        return $fallbackPath ?: 'xl/worksheets/sheet1.xml';
    }

    private function normalizeXlsxWorksheetPath(string $target): string
    {
        if ($target === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        return 'xl/'.ltrim($target, '/');
    }

    private function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! $xml) {
            return [];
        }

        $strings = [];
        $shared = simplexml_load_string($xml);

        foreach ($shared->si ?? [] as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r ?? [] as $run) {
                $text .= (string) ($run->t ?? '');
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function xlsxColumnIndex(string $cellRef): int
    {
        preg_match('/^[A-Z]+/', $cellRef, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function normalizeImportHeader(?string $header): string
    {
        $header = strtolower(trim((string) $header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);

        return trim($header, '_');
    }

    private function combineImportRow(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    private function isBlankImportRow(array $data): bool
    {
        return collect($data)->every(fn ($value) => trim((string) $value) === '');
    }

    private function normalizeImportDate($value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->startOfDay();
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->startOfDay();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeImportTime($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 0 && (float) $value < 1) {
            $seconds = (int) round((float) $value * 86400);

            return gmdate('H:i:s', $seconds);
        }

        if (preg_match('/^\d{1,2}[.:]\d{2}([.:]\d{2})?$/', $value)) {
            $parts = preg_split('/[.:]/', $value);
            $hour = (int) $parts[0];
            $minute = (int) $parts[1];
            $second = (int) ($parts[2] ?? 0);

            if ($hour <= 23 && $minute <= 59 && $second <= 59) {
                return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
            }
        }

        return null;
    }

    private function normalizeImportDurationMinutes($value): int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            $number = (float) $value;

            if ($number > 0 && $number < 1) {
                return (int) round($number * 1440);
            }

            return max(0, (int) round($number));
        }

        if (preg_match('/^\d{1,2}[.:]\d{2}([.:]\d{2})?$/', $value)) {
            $parts = preg_split('/[.:]/', $value);
            $hours = (int) $parts[0];
            $minutes = (int) $parts[1];
            $seconds = (int) ($parts[2] ?? 0);

            return max(0, ($hours * 60) + $minutes + (int) round($seconds / 60));
        }

        return 0;
    }

    private function upsertImportedOvertime(
        Employee $employee,
        Carbon $date,
        array $data,
        string $clockIn,
        string $clockOut,
        int $breakDuration,
        ?string $shiftEndTime
    ): bool {
        $totalDuration = $this->calculateImportedOvertimeDuration($clockIn, $clockOut);

        if ($totalDuration <= 0) {
            return false;
        }

        $breakDuration = min($breakDuration, $totalDuration);
        $actualDuration = max(0, $totalDuration - $breakDuration);
        $overtimeType = $this->importedOvertimeType($data['shift'] ?? null);

        OvertimeRequest::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'date' => $date->format('Y-m-d'),
            ],
            [
                'overtime_type' => $overtimeType,
                'planned_start' => $overtimeType === 'holiday' ? $clockIn : null,
                'planned_end' => $overtimeType === 'holiday' ? $clockOut : null,
                'pre_shift_duration' => 0,
                'pre_shift_break' => 0,
                'post_shift_duration' => $overtimeType === 'workday' ? $totalDuration : 0,
                'post_shift_break' => 0,
                'break_duration' => $breakDuration,
                'total_duration' => $totalDuration,
                'approved_duration' => null,
                'approved_break' => null,
                'actual_duration' => $actualDuration,
                'shift_end_time' => $shiftEndTime,
                'actual_clock_in' => $clockIn,
                'actual_clock_out' => $clockOut,
                'reason' => 'Import attendance overtime',
                'status' => 'approved',
                'current_step' => 1,
            ]
        );

        return true;
    }

    private function calculateImportedOvertimeDuration(string $clockIn, string $clockOut): int
    {
        $start = Carbon::createFromFormat('H:i:s', $clockIn);
        $end = Carbon::createFromFormat('H:i:s', $clockOut);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }

    private function importedOvertimeType($shift): string
    {
        $shift = strtolower(trim((string) $shift));
        $normalizedShift = preg_replace('/[^a-z0-9]+/', '_', $shift);

        if (str_contains($shift, 'holiday') || str_contains($shift, 'libur') || str_contains($normalizedShift, 'dayoff') || str_contains($normalizedShift, 'day_off')) {
            return 'holiday';
        }

        if (in_array($normalizedShift, ['off', 'day_off', 'dayoff', 'national_holiday'], true)) {
            return 'holiday';
        }

        return 'workday';
    }

    private function isLateForImportedAttendance(Employee $employee, Carbon $date, ?string $clockIn, $importedShift = null): bool
    {
        if (! $clockIn) {
            return false;
        }

        $employee->loadMissing('scheduleTemplate.days.shift');
        $override = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->where('date', $date->format('Y-m-d'))
            ->first();

        if ($override?->shift) {
            $shift = $override->shift;

            if ($shift->is_off || ! $shift->start_time) {
                return false;
            }

            return $clockIn > substr($shift->start_time, 0, 8);
        }

        if ($this->importedOvertimeType($importedShift) === 'holiday') {
            return false;
        }

        $holiday = Holiday::where('company_id', $employee->company_id)
            ->where('date', $date->format('Y-m-d'))
            ->exists();

        if ($holiday) {
            return false;
        }

        $shift = $employee->scheduleTemplate?->getShiftForDay($date->dayOfWeekIso);

        if (! $shift || $shift->is_off || ! $shift->start_time) {
            return false;
        }

        return $clockIn > substr($shift->start_time, 0, 8);
    }

    public function employeeDetail(Request $request, $id)
    {
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::where('company_id', $admin->company_id)
            ->with(['department:id,name', 'scheduleTemplate.days.shift'])
            ->findOrFail($id);

        $period = $request->period ? Carbon::parse($request->period . '-01') : Carbon::today()->startOfMonth();
        $startOfMonth = $period->copy()->startOfMonth();
        $endOfMonth = $period->copy()->endOfMonth();
        $daysInMonth = $period->daysInMonth;

        // Load template days
        $templateDays = [];
        if ($employee->scheduleTemplate) {
            foreach ($employee->scheduleTemplate->days as $day) {
                $templateDays[$day->day_of_week] = $day->shift;
            }
        }

        // Load overrides
        $overrides = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($o) => $o->date->format('Y-m-d'));

        // Load attendances
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($a) => $a->date->format('Y-m-d'));

        // Load holidays
        $holidays = Holiday::where('company_id', $admin->company_id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($h) => $h->date->format('Y-m-d'));

        // Load leaves
        $leaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $endOfMonth->format('Y-m-d'))
            ->where('end_date', '>=', $startOfMonth->format('Y-m-d'))
            ->get();

        $dayNames = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        $rows = [];
        $stats = ['hadir' => 0, 'terlambat' => 0, 'wfh' => 0, 'sakit' => 0, 'alpha' => 0, 'cuti' => 0, 'off' => 0, 'libur' => 0];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $startOfMonth->copy()->addDays($d - 1);
            $dateStr = $date->format('Y-m-d');
            $dow = $date->dayOfWeekIso;

            $holiday = $holidays[$dateStr] ?? null;
            $att = $attendances[$dateStr] ?? null;

            // Manual override wins over holiday; template only applies on non-holidays.
            $shift = isset($overrides[$dateStr]) ? $overrides[$dateStr]->shift : null;
            if (!$shift && !$holiday) {
                $shift = $templateDays[$dow] ?? null;
            }

            // Check leave
            $leave = AttendanceLateExcuse::firstForDate($leaves, $date);
            $lateExcuse = AttendanceLateExcuse::isLateArrivalLeave($leave) ? $leave : null;
            $earlyDeparture = AttendanceLateExcuse::isEarlyDepartureLeave($leave) ? $leave : null;
            $partialDayLeave = $lateExcuse || $earlyDeparture;

            $status = 'no_schedule';
            $statusLabel = '-';
            if ($holiday && !$shift) {
                $status = 'holiday';
                $statusLabel = $holiday->name;
                $stats['libur']++;
            } elseif ($leave && !$partialDayLeave) {
                if ($this->isSickLeave($leave)) {
                    $status = 'sick';
                    $statusLabel = 'Sakit';
                    $stats['sakit']++;
                } elseif (AttendanceLateExcuse::isWfhLeave($leave)) {
                    $status = 'wfh';
                    $statusLabel = 'WFH';
                    $stats['wfh']++;
                } else {
                    $status = 'leave';
                    $statusLabel = $leave->leaveType->name ?? 'Cuti';
                    $stats['cuti']++;
                }
            } elseif ($shift && $shift->is_off) {
                $status = 'off';
                $statusLabel = 'OFF';
                $stats['off']++;
            } elseif ($att && $att->status === 'sick') {
                $status = 'sick';
                $statusLabel = 'Sakit';
                $stats['sakit']++;
            } elseif ($att && $att->status === 'wfh') {
                $status = 'wfh';
                $statusLabel = 'WFH';
                $stats['wfh']++;
            } elseif ($att && AttendanceLateExcuse::manualPermissionStatusLabel($att->status)) {
                $status = 'present';
                $statusLabel = AttendanceLateExcuse::manualPermissionStatusLabel($att->status);
                $stats['hadir']++;
            } elseif ($att && $att->status === 'present') {
                if ($att->is_late && !$lateExcuse) {
                    $status = 'late';
                    $statusLabel = 'Terlambat';
                    $stats['terlambat']++;
                } else {
                    $status = 'present';
                    $statusLabel = $att->is_late && $lateExcuse
                        ? AttendanceLateExcuse::STATUS_LABEL
                        : ($earlyDeparture ? AttendanceLateExcuse::EARLY_DEPARTURE_STATUS_LABEL : 'Hadir');
                }
                $stats['hadir']++;
            } elseif ($shift && !$shift->is_off && $date->lte(Carbon::today())) {
                $status = 'absent';
                $statusLabel = 'Alpha';
                $stats['alpha']++;
            } elseif ($shift && !$shift->is_off) {
                $status = 'scheduled';
                $statusLabel = 'Terjadwal';
            }

            $rows[] = [
                'date' => $date,
                'day' => $d,
                'day_name' => $dayNames[$dow],
                'shift' => $shift,
                'attendance' => $att,
                'holiday' => $holiday,
                'leave' => $leave,
                'late_excuse' => $lateExcuse,
                'status' => $status,
                'status_label' => $statusLabel,
            ];
        }

        return view('admin.attendance-recap.employee-detail', compact(
            'employee', 'period', 'rows', 'stats'
        ));
    }

    private function isSickLeave(?LeaveRequest $leave): bool
    {
        $name = Str::lower((string) ($leave?->leaveType?->name ?? ''));

        return Str::contains($name, ['sakit', 'sick', 'medical']);
    }
}

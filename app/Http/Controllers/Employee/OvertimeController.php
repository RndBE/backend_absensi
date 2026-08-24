<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Support\ScheduledWorkingDays;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OvertimeController extends Controller
{
    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        // Tanpa ?period= → tampilkan SEMUA riwayat. Dengan ?period=YYYY-MM → filter bulan itu.
        $period = $request->query('period') ? Carbon::parse($request->query('period').'-01') : null;

        return view('employee.overtimes.index', [
            'employee' => $employee,
            'requests' => OvertimeRequest::where('employee_id', $employee->id)
                ->when($period, fn ($q) => $q->whereYear('created_at', $period->year)->whereMonth('created_at', $period->month))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'period' => $period,
        ]);
    }

    public function create(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.overtimes.create', [
            'employee' => $employee,
        ]);
    }

    public function show(Request $request, int $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $overtime = $this->findOwnedOvertime($employee, $id)->load('approvalLogs.approver');

        return view('employee.overtimes.show', [
            'employee' => $employee,
            'overtime' => $overtime,
        ]);
    }

    public function edit(Request $request, int $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $overtime = $this->findOwnedOvertime($employee, $id);

        if ($overtime->status !== 'pending') {
            return redirect()
                ->route('employee.overtimes.show', $overtime->id)
                ->with('error', 'Pengajuan lembur yang sudah diproses tidak dapat diedit.');
        }

        return view('employee.overtimes.edit', [
            'employee' => $employee,
            'overtime' => $overtime,
        ]);
    }

    /**
     * Jam clock-in/out aktual karyawan pada tanggal tertentu — untuk auto-isi
     * jam mulai/selesai lembur hari libur.
     */
    public function attendanceTimes(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $request->query('date'))
            ->first();

        return response()->json([
            'found'     => (bool) $attendance,
            'clock_in'  => $attendance && $attendance->clock_in ? substr($attendance->clock_in, 0, 5) : null,
            'clock_out' => $attendance && $attendance->clock_out ? substr($attendance->clock_out, 0, 5) : null,
        ]);
    }

    /**
     * Tipe lembur otomatis dari tanggal: hari kerja terjadwal → "workday",
     * selain itu (off/libur) → "holiday". Untuk auto-isi selector di form.
     */
    public function dayType(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $isWorking = ScheduledWorkingDays::isWorkingDate($employee, Carbon::parse($request->query('date')));

        return response()->json([
            'overtime_type' => $isWorking ? 'workday' : 'holiday',
            'is_working_day' => $isWorking,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedOvertime($request);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        // Satu hari satu pengajuan. Seluruh pemeriksaan sampai penyimpanan dibungkus
        // transaksi supaya dua kiriman yang datang pada detik yang sama tidak sama-sama
        // lolos pemeriksaan sebelum salah satunya sempat menyimpan.
        $ditolak = DB::transaction(function () use ($employee, $validated) {
            $kembar = $this->cariPengajuanBentrok($employee, $validated['date'], null, true);

            if ($kembar) {
                return $kembar;
            }

            OvertimeRequest::create(array_merge(
                ['employee_id' => $employee->id, 'status' => 'pending', 'current_step' => 1],
                $this->overtimeAttributes($validated)
            ));

            return null;
        });

        if ($ditolak) {
            return back()->withInput()->with('error', $this->pesanBentrok($ditolak));
        }

        return redirect()
            ->route('employee.overtimes.index')
            ->with('success', 'Pengajuan lembur berhasil dikirim.');
    }

    public function update(Request $request, int $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $overtime = $this->findOwnedOvertime($employee, $id);

        if ($overtime->status !== 'pending') {
            return redirect()
                ->route('employee.overtimes.show', $overtime->id)
                ->with('error', 'Pengajuan lembur yang sudah diproses tidak dapat diedit.');
        }

        $validated = $this->validatedOvertime($request);

        // Tanggal boleh diubah saat mengedit, jadi pemeriksaan bentrok berlaku juga di sini
        // -- dirinya sendiri dikecualikan.
        $kembar = $this->cariPengajuanBentrok($employee, $validated['date'], $overtime->id);

        if ($kembar) {
            return back()->withInput()->with('error', $this->pesanBentrok($kembar));
        }

        $overtime->update($this->overtimeAttributes($validated));

        return redirect()
            ->route('employee.overtimes.show', $overtime->id)
            ->with('success', 'Pengajuan lembur berhasil diperbarui.');
    }

    private function findOwnedOvertime(Employee $employee, int $id): OvertimeRequest
    {
        return OvertimeRequest::where('employee_id', $employee->id)->findOrFail($id);
    }

    /**
     * Cari pengajuan lembur milik karyawan ini yang bentrok pada tanggal yang sama.
     *
     * Kuncinya TANGGAL, bukan jam. Alasannya bukan penyederhanaan: `planned_start` hanya
     * terisi di sebagian kecil baris (mayoritas lembur hari kerja tidak memakainya sama
     * sekali), jadi kunci berbasis jam akan meloloskan justru sebagian besar duplikat.
     * Lembur dua sesi dalam sehari pun tidak butuh dua baris -- `pre_shift_duration` dan
     * `post_shift_duration` sudah menampung keduanya sekaligus.
     *
     * Yang TIDAK dihitung bentrok:
     * - Status `rejected`. Ditolak lalu diperbaiki dan diajukan ulang adalah alur yang sah
     *   dan justru yang paling sering dipakai; memblokirnya akan mematikan satu-satunya
     *   jalan karyawan membetulkan pengajuannya.
     * - Lembur otomatis (`current_step` = 0, dibuat AutoOvertimeService). Baris itu catatan
     *   sistem atas shift panjang dan menit dibayarnya nol; ia tidak boleh menghalangi
     *   karyawan mengajukan lembur nyatanya di hari yang sama.
     *
     * @param  bool  $kunci  Kunci baris terpilih sampai transaksi selesai, dipakai saat
     *                       menyimpan supaya dua kiriman serentak tidak sama-sama lolos.
     */
    private function cariPengajuanBentrok(Employee $employee, string $date, ?int $kecuali = null, bool $kunci = false): ?OvertimeRequest
    {
        $query = OvertimeRequest::where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->where('status', '!=', 'rejected')
            ->where('current_step', '!=', 0)
            ->when($kecuali, fn ($q) => $q->where('id', '!=', $kecuali));

        if ($kunci) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Pesan penolakan menyebut STATUS pengajuan yang sudah ada, bukan sekadar "sudah ada".
     * Tindakan karyawan berbeda-beda menurut status itu: yang masih menunggu tinggal
     * diedit, yang sudah disetujui berarti tidak perlu apa-apa lagi.
     */
    private function pesanBentrok(OvertimeRequest $kembar): string
    {
        $tanggal = Carbon::parse($kembar->date)->locale('id')->translatedFormat('j F Y');

        $keterangan = match ($kembar->status) {
            'approved' => 'sudah disetujui',
            'in_review' => 'sedang ditinjau',
            default => 'masih menunggu persetujuan',
        };

        return "Anda sudah punya pengajuan lembur untuk {$tanggal} yang {$keterangan}. "
            .'Satu hari hanya boleh satu pengajuan — ubah pengajuan yang sudah ada bila perlu, '
            .'termasuk bila lemburnya lebih dari satu sesi.';
    }

    private function validatedOvertime(Request $request): array
    {
        $durationRule = ['nullable', 'regex:/^(?:\d+|(?:[01]\d|2[0-3]):[0-5]\d)$/'];

        return $request->validate([
            'date' => 'required|date',
            'overtime_type' => 'required|in:workday,holiday',
            'planned_start' => 'required_if:overtime_type,holiday|nullable|date_format:H:i',
            'planned_end' => 'required_if:overtime_type,holiday|nullable|date_format:H:i',
            'pre_shift_duration' => $durationRule,
            'pre_shift_break' => $durationRule,
            'post_shift_duration' => $durationRule,
            'post_shift_break' => $durationRule,
            'break_duration' => $durationRule,
            'reason' => 'required|string|max:1000',
        ]);
    }

    private function overtimeAttributes(array $validated): array
    {
        if ($validated['overtime_type'] === 'holiday') {
            $start = Carbon::parse($validated['planned_start']);
            $end = Carbon::parse($validated['planned_end']);
            if ($end->lessThan($start)) {
                $end->addDay(); // lembur melewati tengah malam
            }

            return [
                'date' => $validated['date'],
                'overtime_type' => 'holiday',
                'planned_start' => $validated['planned_start'],
                'planned_end' => $validated['planned_end'],
                'pre_shift_duration' => 0,
                'pre_shift_break' => 0,
                'post_shift_duration' => 0,
                'post_shift_break' => 0,
                'break_duration' => $this->durationToMinutes($validated['break_duration'] ?? 0),
                'total_duration' => (int) $start->diffInMinutes($end),
                'reason' => $validated['reason'],
            ];
        }

        $preDuration = $this->durationToMinutes($validated['pre_shift_duration'] ?? 0);
        $preBreak = $this->durationToMinutes($validated['pre_shift_break'] ?? 0);
        $postDuration = $this->durationToMinutes($validated['post_shift_duration'] ?? 0);
        $postBreak = $this->durationToMinutes($validated['post_shift_break'] ?? 0);

        return [
            'date' => $validated['date'],
            'overtime_type' => 'workday',
            'planned_start' => null,
            'planned_end' => null,
            'pre_shift_duration' => $preDuration,
            'pre_shift_break' => $preBreak,
            'post_shift_duration' => $postDuration,
            'post_shift_break' => $postBreak,
            'break_duration' => $preBreak + $postBreak,
            'total_duration' => $preDuration + $postDuration,
            'reason' => $validated['reason'],
        ];
    }

    private function durationToMinutes($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):([0-5]\d)$/', $value, $matches)) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }

        return 0;
    }
}

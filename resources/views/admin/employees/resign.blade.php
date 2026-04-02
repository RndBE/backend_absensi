@extends('admin.layouts.app')
@section('title', 'Proses Resign — ' . $employee->full_name)

@section('content')

{{-- ── BREADCRUMB ── --}}
<div class="mb-5 flex items-center gap-3 flex-wrap">
    <a href="{{ route('admin.employees.index') }}"
       class="inline-flex items-center gap-1 text-[13px] text-gray-500 hover:text-red-600 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Karyawan
    </a>
    <span class="text-gray-300">/</span>
    <a href="{{ route('admin.employees.show', $employee->id) }}"
       class="text-[13px] text-gray-500 hover:text-gray-700">{{ $employee->full_name }}</a>
    <span class="text-gray-300">/</span>
    <span class="text-[13px] font-semibold text-red-600">Proses Resign</span>
</div>

{{-- ── ALERT KONFIRMASI ── --}}
<div class="mb-5 flex items-start gap-3 px-5 py-4 rounded-xl bg-red-50 border border-red-200">
    <span class="material-symbols-outlined text-red-500 text-[22px] mt-0.5 shrink-0">warning</span>
    <div>
        <p class="text-[13px] font-bold text-red-700">Perhatian — Tindakan Ini Tidak Dapat Dibatalkan</p>
        <p class="text-[12px] text-red-600 mt-0.5">
            Proses ini akan menonaktifkan akun karyawan, menghentikan semua komponen gaji, dan menonaktifkan master payroll.
            Pastikan semua data sudah benar sebelum melanjutkan.
        </p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- FORM RESIGN                                               --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div class="xl:col-span-2">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-500 text-[17px]">person_remove</span>
                </div>
                <div>
                    <h3 class="text-[14px] font-bold text-gray-800">Form Proses Resign</h3>
                    <p class="text-[11px] text-gray-400">{{ $employee->full_name }} &bull; {{ $employee->employee_code }}</p>
                </div>
            </div>

            <form action="{{ route('admin.employees.process-resign', $employee->id) }}" method="POST"
                  onsubmit="return confirm('Yakin ingin memproses resign karyawan ini? Tindakan tidak dapat dibatalkan.')">
                @csrf

                <div class="p-6 space-y-5">

                    {{-- Tipe Resign --}}
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-2">Alasan Resign *</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            @php
                                $reasons = [
                                    'voluntary'    => ['label' => 'Pengunduran Diri', 'icon' => 'exit_to_app',   'color' => 'indigo'],
                                    'termination'  => ['label' => 'PHK / Pemutusan',  'icon' => 'gavel',         'color' => 'red'],
                                    'contract_end' => ['label' => 'Kontrak Berakhir', 'icon' => 'event_busy',    'color' => 'amber'],
                                    'retirement'   => ['label' => 'Pensiun',           'icon' => 'elderly',       'color' => 'emerald'],
                                    'passed_away'  => ['label' => 'Meninggal Dunia',   'icon' => 'sentiment_sad', 'color' => 'gray'],
                                ];
                            @endphp
                            @foreach($reasons as $val => $r)
                            <label class="reason-card cursor-pointer">
                                <input type="radio" name="resign_reason" value="{{ $val }}" class="reason-radio sr-only"
                                       {{ old('resign_reason') === $val ? 'checked' : '' }}>
                                <div class="reason-box flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 border-gray-200
                                            hover:border-{{ $r['color'] }}-300 hover:bg-{{ $r['color'] }}-50 transition-all text-center"
                                     data-color="{{ $r['color'] }}">
                                    <span class="material-symbols-outlined text-[22px] text-gray-400">{{ $r['icon'] }}</span>
                                    <span class="text-[11.5px] font-semibold text-gray-600 leading-tight">{{ $r['label'] }}</span>
                                </div>
                            </label>
                            @endforeach
                        </div>
                        @error('resign_reason')
                        <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tanggal --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">
                                Tanggal Resign / Surat *
                                <span class="font-normal text-gray-400">(tanggal surat atau keputusan)</span>
                            </label>
                            <input type="date" name="resign_date" id="resignDate"
                                   value="{{ old('resign_date', date('Y-m-d')) }}" required
                                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none">
                            @error('resign_date')
                            <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">
                                Hari Kerja Terakhir *
                            </label>
                            <input type="date" name="last_working_date" id="lastWorkingDate"
                                   value="{{ old('last_working_date', date('Y-m-d')) }}" required
                                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none">
                            @error('last_working_date')
                            <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Keterangan --}}
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">
                            Keterangan / Catatan
                            <span class="font-normal text-gray-400">(opsional — misal: alasan detail, nomor surat)</span>
                        </label>
                        <textarea name="resign_notes" rows="3" maxlength="1000"
                                  placeholder="Contoh: Karyawan mengundurkan diri atas permintaan sendiri. No. Surat: ..."
                                  class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none resize-none">{{ old('resign_notes') }}</textarea>
                    </div>

                    {{-- Checklist Konfirmasi --}}
                    <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 space-y-2.5">
                        <p class="text-[11.5px] font-bold text-gray-600 mb-3 uppercase tracking-wide">Konfirmasi Checklist</p>
                        @php
                            $checks = [
                                'check1' => 'Saya telah memastikan tanggal resign dan hari terakhir bekerja sudah benar',
                                'check2' => 'Saya memahami bahwa akun karyawan akan dinonaktifkan secara permanen',
                                'check3' => 'Semua komponen gaji karyawan ini akan dihentikan otomatis',
                                'check4' => 'Proses perhitungan PPh 21 bulan terakhir sudah dicatat/direncanakan',
                            ];
                        @endphp
                        @foreach($checks as $name => $label)
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" name="{{ $name }}" value="1" required
                                   class="confirm-check mt-0.5 w-4 h-4 rounded accent-red-600">
                            <span class="text-[12px] text-gray-600">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex items-center justify-between pt-2">
                        <a href="{{ route('admin.employees.show', $employee->id) }}"
                           class="px-5 py-2.5 text-[13px] font-semibold text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition">
                            Batal
                        </a>
                        <button type="submit" id="submitBtn" disabled
                                class="inline-flex items-center gap-2 px-6 py-2.5 text-[13px] font-semibold text-white
                                       bg-red-600 rounded-xl hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed
                                       transition-all shadow-sm">
                            <span class="material-symbols-outlined text-[16px]">person_remove</span>
                            Proses Resign
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- PANEL KANAN: Info Karyawan + Preview PPh21                --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div class="xl:col-span-1 space-y-4">

        {{-- Info karyawan --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h4 class="text-[13px] font-bold text-gray-700">Info Karyawan</h4>
            </div>
            <div class="p-5 space-y-3">
                @php
                    $payroll = $employee->activePayroll;
                    $infoRows = [
                        'Kode'         => $employee->employee_code,
                        'Departemen'   => $employee->department->name ?? '-',
                        'Jabatan'      => $employee->position ?? '-',
                        'Status'       => ucfirst($employee->employment_status ?? '-'),
                        'Tanggal Masuk'=> $employee->join_date?->format('d M Y') ?? '-',
                        'Masa Kerja'   => $employee->masa_kerja,
                        'Gaji Pokok'   => $payroll ? 'Rp ' . number_format($payroll->basic_salary, 0, ',', '.') : '-',
                        'PTKP'         => $payroll->ptkp_status ?? '-',
                    ];
                @endphp
                @foreach($infoRows as $label => $val)
                <div class="flex justify-between gap-2">
                    <span class="text-[11.5px] text-gray-400 shrink-0">{{ $label }}</span>
                    <span class="text-[11.5px] font-semibold text-gray-700 text-right">{{ $val }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Preview PPh21 Bulan Terakhir --}}
        @if($pph21Preview)
        <div class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-amber-100 bg-amber-50">
                <h4 class="text-[13px] font-bold text-amber-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">calculate</span>
                    Preview PPh 21 Bulan Terakhir
                </h4>
                <p class="text-[11px] text-amber-600 mt-0.5">
                    Berdasarkan {{ $monthsWorked }} bulan bekerja di tahun {{ date('Y') }}
                </p>
            </div>
            <div class="p-5 space-y-2.5">
                @php
                    $pphRows = [
                        'Avg. Bruto/bln'       => 'Rp ' . number_format($pph21Preview['avg_bruto_monthly'], 0, ',', '.'),
                        'Biaya Jabatan 5%'     => 'Rp ' . number_format($pph21Preview['biaya_jabatan'], 0, ',', '.'),
                        'BPJS Karyawan'        => 'Rp ' . number_format($pph21Preview['bpjs_employee'], 0, ',','.'),
                        'Netto/bln'            => 'Rp ' . number_format($pph21Preview['netto_monthly'], 0, ',', '.'),
                        'Netto × ' . $monthsWorked . ' bln' => 'Rp ' . number_format($pph21Preview['netto_annualized'], 0, ',', '.'),
                        'PTKP (' . $pph21Preview['ptkp_status'] . ')' => 'Rp ' . number_format($pph21Preview['ptkp_annual'], 0, ',', '.'),
                        'PKP'                  => 'Rp ' . number_format($pph21Preview['pkp'], 0, ',', '.'),
                        'Pajak terhutang'      => 'Rp ' . number_format($pph21Preview['tax_for_period'], 0, ',', '.'),
                    ];
                @endphp
                @foreach($pphRows as $label => $val)
                <div class="flex justify-between gap-2">
                    <span class="text-[11px] text-gray-400">{{ $label }}</span>
                    <span class="text-[11px] font-semibold text-gray-700">{{ $val }}</span>
                </div>
                @endforeach
                <div class="pt-2 mt-1 border-t border-amber-200 flex justify-between gap-2">
                    <span class="text-[12px] font-bold text-amber-800">PPh 21 Bulan Terakhir</span>
                    <span class="text-[13px] font-bold text-amber-700">
                        Rp {{ number_format($pph21Preview['tax_final_month'], 0, ',', '.') }}
                    </span>
                </div>
                <p class="text-[10px] text-amber-500 leading-relaxed">
                    * Dihitung berdasarkan {{ $monthsWorked }} bulan aktual (bukan disetahunkan × 12),
                    sesuai PMK-168/PMK.03/2023. Nominal final mungkin berbeda jika ada pajak yang sudah dibayar bulan sebelumnya.
                </p>
            </div>
        </div>
        @endif

    </div>
</div>

<style>
.reason-radio:checked + .reason-box {
    border-color: #ef4444;
    background-color: #fef2f2;
}
.reason-radio:checked + .reason-box span.material-symbols-outlined {
    color: #ef4444;
}
.reason-radio:checked + .reason-box span:last-child {
    color: #b91c1c;
}
</style>

<script>
// Enable submit only when all checkboxes checked AND reason selected
function checkForm() {
    const checks = document.querySelectorAll('.confirm-check');
    const allChecked = [...checks].every(c => c.checked);
    const reasonSelected = document.querySelector('.reason-radio:checked');
    document.getElementById('submitBtn').disabled = !(allChecked && reasonSelected);
}

document.querySelectorAll('.confirm-check').forEach(cb => cb.addEventListener('change', checkForm));
document.querySelectorAll('.reason-radio').forEach(r  => r.addEventListener('change', checkForm));

// Auto-set last_working_date when resign_date changes (default: same day)
document.getElementById('resignDate').addEventListener('change', function () {
    const lwd = document.getElementById('lastWorkingDate');
    if (!lwd.value || lwd.value < this.value) lwd.value = this.value;
});
</script>
@endsection

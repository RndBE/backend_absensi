@extends('employee.layouts.app')
@section('title', 'Riwayat Presensi — ' . $member->full_name)

@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('employee.team-attendance.index') }}"
           class="mb-2 inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Presensi Tim
        </a>
        <h1 class="text-[22px] font-black text-gray-900">{{ $member->full_name }}</h1>
        <p class="mt-1 text-[13px] text-gray-500">{{ $member->department?->name ?? '-' }} · {{ $period->locale('id')->translatedFormat('F Y') }}</p>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3">
        <label class="text-[11px] font-bold uppercase text-gray-400">Periode</label>
        <input type="month" name="period" value="{{ $period->format('Y-m') }}" onchange="this.form.submit()"
            class="rounded-lg border border-gray-300 px-3 py-1.5 text-[12.5px] outline-none focus:border-indigo-500">
    </form>

    {{-- Rekap bulan ini --}}
    <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
        @foreach([
            ['Hadir', $stats['hadir'], 'text-emerald-700', 'bg-emerald-50'],
            ['Terlambat', $stats['terlambat'], 'text-amber-700', 'bg-amber-50'],
            ['Alpha', $stats['alpha'], 'text-red-700', 'bg-red-50'],
            ['Cuti', $stats['cuti'], 'text-blue-700', 'bg-blue-50'],
            ['Off', $stats['off'], 'text-gray-600', 'bg-gray-50'],
            ['Libur', $stats['libur'], 'text-gray-600', 'bg-gray-50'],
        ] as [$label, $nilai, $warna, $latar])
            <div class="rounded-xl border border-gray-200 {{ $latar }} px-3 py-2.5 text-center">
                <div class="text-[19px] font-black {{ $warna }}">{{ $nilai }}</div>
                <div class="text-[10.5px] font-bold uppercase text-gray-400">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    {{-- Riwayat harian --}}
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        @foreach($days as $day)
            @php
                $att = $day['attendance'];
                $shift = $day['shift'];
                $sudahLewat = $day['date'] <= now()->toDateString();

                if ($day['holiday'] && ! $shift) {
                    [$label, $kelas] = [$day['holiday'], 'bg-red-50 text-red-700'];
                } elseif ($day['leave']) {
                    [$label, $kelas] = ['Cuti: '.$day['leave']['type'], 'bg-blue-50 text-blue-700'];
                } elseif ($shift && $shift['is_off']) {
                    [$label, $kelas] = ['Off', 'bg-gray-100 text-gray-500'];
                } elseif ($att) {
                    [$label, $kelas] = [$att['status_label'], $att['is_late'] ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'];
                } elseif ($shift && $sudahLewat) {
                    [$label, $kelas] = ['Alpha', 'bg-red-50 text-red-700'];
                } else {
                    [$label, $kelas] = [$shift ? 'Belum' : 'Tidak Ada Jadwal', 'bg-gray-50 text-gray-400'];
                }
            @endphp
            <div class="flex items-center gap-3 border-b border-gray-100 px-3 py-2.5 last:border-b-0 sm:px-4 {{ $day['is_today'] ? 'bg-indigo-50/40' : '' }}">
                {{-- Tanggal: sempit dan tetap, supaya kolom lain tidak bergeser-geser --}}
                <div class="w-11 shrink-0 text-center sm:w-16 sm:text-left">
                    <div class="text-[13px] font-bold text-gray-800">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d/m') }}</div>
                    <div class="text-[10px] text-gray-400">{{ \Illuminate\Support\Str::substr($day['day_name'], 0, 3) }}</div>
                </div>

                <div class="min-w-0 flex-1">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold {{ $kelas }}">{{ $label }}</span>
                    {{-- Jam shift disembunyikan di HP: yang penting jam absen sebenarnya --}}
                    @if($shift && ! $shift['is_off'])
                        <div class="mt-0.5 hidden text-[11.5px] text-gray-400 sm:block">
                            Shift {{ $shift['start_time'] }}–{{ $shift['end_time'] }}
                        </div>
                    @endif
                </div>

                <div class="shrink-0 text-right">
                    @if($att)
                        <div class="whitespace-nowrap text-[12.5px] font-semibold text-gray-700">
                            {{ $att['clock_in'] ? substr($att['clock_in'], 0, 5) : '--:--' }}
                            <span class="text-gray-300">&rarr;</span>
                            {{ $att['clock_out'] ? substr($att['clock_out'], 0, 5) : '--:--' }}
                        </div>
                        @if($att['is_remote'])
                            <span class="mt-0.5 inline-block rounded bg-purple-50 px-1.5 py-0.5 text-[10px] font-bold text-purple-700">Remote</span>
                        @endif
                    @else
                        <span class="text-[12.5px] text-gray-300">&mdash;</span>
                    @endif
                </div>
            </div>
        @endforeach
    </section>
</div>
@endsection

@extends('employee.layouts.app')
@section('title', 'Presensi Tim')

@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('employee.dashboard') }}" class="mb-2 inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Dashboard
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Presensi Tim</h1>
        <p class="mt-1 text-[13px] text-gray-500">{{ now()->locale('id')->translatedFormat('l, d F Y') }} · {{ $departments->implode(', ') }}</p>
    </div>

    @if($rows->isEmpty())
        <section class="rounded-xl border border-gray-200 bg-white p-10 text-center shadow-sm">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <span class="material-symbols-outlined text-[24px]">groups</span>
            </div>
            <h2 class="mt-3 text-[15px] font-black text-gray-900">Belum ada anggota tim</h2>
            <p class="mt-1 text-[13px] text-gray-500">Tidak ada karyawan aktif di departemen Anda maupun turunannya.</p>
        </section>
    @else

    {{-- Ringkasan hari ini --}}
    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
            @foreach([
                ['Sudah hadir', $todaySummary['hadir'], 'text-emerald-700', 'bg-emerald-50'],
                ['Terlambat', $todaySummary['telat'], 'text-amber-700', 'bg-amber-50'],
                ['Belum absen', $todaySummary['terlewat'], 'text-red-700', 'bg-red-50'],
                ['Belum waktunya', $todaySummary['belum'], 'text-gray-600', 'bg-gray-50'],
                ['Tidak masuk', $todaySummary['tak_masuk'], 'text-blue-700', 'bg-blue-50'],
            ] as [$label, $nilai, $warna, $latar])
                <div class="rounded-lg {{ $latar }} px-3 py-2 text-center">
                    <div class="text-[18px] font-black {{ $warna }}">{{ $nilai }}</div>
                    <div class="text-[10px] font-bold uppercase leading-tight text-gray-400">{{ $label }}</div>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-[11.5px] text-gray-400">
            {{ $rows->count() }} anggota tim · ketuk salah satu untuk melihat rekap & riwayat bulanannya
        </p>
    </section>

    @php
        // Nada warna status hari ini — dipakai kartu (HP) maupun tabel (layar lebar).
        $tone = fn (string $t) => match ($t) {
            'hadir' => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500'],
            'telat' => ['bg-amber-50 text-amber-700', 'bg-amber-500'],
            'terlewat' => ['bg-red-50 text-red-700', 'bg-red-500'],
            'izin' => ['bg-blue-50 text-blue-700', 'bg-blue-400'],
            'off', 'libur' => ['bg-gray-100 text-gray-500', 'bg-gray-300'],
            default => ['bg-gray-50 text-gray-400', 'bg-gray-300'],
        };
    @endphp

    {{-- HP: kartu bertumpuk, tanpa scroll horizontal --}}
    <section class="space-y-2 lg:hidden">
        @foreach($rows as $row)
            @php
                $m = $row['employee'];
                $t = $row['today'];
                [$kelasBadge, $kelasTitik] = $tone($t['tone']);
            @endphp
            <a href="{{ route('employee.team-attendance.show', $m->id) }}"
               class="flex items-center gap-2.5 rounded-xl border bg-white p-3.5 shadow-sm transition-colors active:bg-gray-50 {{ $t['tone'] === 'terlewat' ? 'border-red-200' : 'border-gray-200' }}">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 text-[13px] font-bold text-white">
                    {{ mb_substr($m->full_name, 0, 1) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-[13.5px] font-bold text-gray-900">{{ $m->full_name }}</div>
                    <div class="truncate text-[11px] text-gray-400">{{ $m->department?->name ?? '-' }}</div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11.5px] font-bold {{ $kelasBadge }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $kelasTitik }}"></span>
                            {{ $t['label'] }}
                        </span>
                        @if($t['clock_in'])
                            <span class="text-[11.5px] text-gray-500">
                                {{ $t['clock_in'] }} <span class="text-gray-300">&rarr;</span> {{ $t['clock_out'] ?? '--:--' }}
                            </span>
                        @endif
                    </div>
                </div>
                <span class="material-symbols-outlined shrink-0 text-[18px] text-gray-300">chevron_right</span>
            </a>
        @endforeach
    </section>

    {{-- Layar lebar: tabel ringkas, 4 kolom --}}
    <section class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:block">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="border-b border-gray-200 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500">Karyawan</th>
                    <th class="border-b border-gray-200 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-indigo-600">Status Hari Ini</th>
                    <th class="border-b border-gray-200 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500">Jam Absen</th>
                    <th class="border-b border-gray-200 px-3 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php
                        $m = $row['employee'];
                        $t = $row['today'];
                        [$kelasBadge, $kelasTitik] = $tone($t['tone']);
                    @endphp
                    <tr class="transition-colors hover:bg-gray-50 {{ $t['tone'] === 'terlewat' ? 'bg-red-50/30' : '' }}">
                        <td class="border-b border-gray-100 px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 text-[12px] font-bold text-white">
                                    {{ mb_substr($m->full_name, 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate text-[13px] font-semibold text-gray-800">{{ $m->full_name }}</div>
                                    <div class="truncate text-[11px] text-gray-400">{{ $m->department?->name ?? '-' }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="border-b border-gray-100 px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11.5px] font-bold {{ $kelasBadge }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $kelasTitik }}"></span>
                                {{ $t['label'] }}
                            </span>
                        </td>

                        <td class="border-b border-gray-100 px-4 py-3 text-[13px] text-gray-700">
                            @if($t['clock_in'])
                                <span class="font-semibold">{{ $t['clock_in'] }}</span>
                                <span class="text-gray-300">&rarr;</span>
                                <span class="font-semibold">{{ $t['clock_out'] ?? '--:--' }}</span>
                            @else
                                <span class="text-gray-300">&mdash;</span>
                            @endif
                        </td>

                        <td class="border-b border-gray-100 px-3 py-3 text-right">
                            <a href="{{ route('employee.team-attendance.show', $m->id) }}"
                               class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-[11.5px] font-bold text-indigo-700 transition-colors hover:bg-indigo-100">
                                Rekap &amp; Riwayat
                                <span class="material-symbols-outlined text-[15px]">chevron_right</span>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
    @endif
</div>
@endsection

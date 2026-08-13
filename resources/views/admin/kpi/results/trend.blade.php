@extends('admin.layouts.app')
@section('title', 'Tren Skor KPI')

@section('content')
@php
    // Bar dibuat relatif terhadap skala penuh 1–5 supaya panjangnya bisa dibandingkan
    // antar periode, bukan terhadap nilai tertinggi yang kebetulan muncul.
    $barWidth = fn ($value) => $value === null ? 0 : max(0, min(100, ((float) $value) / 5 * 100));
    $fmt = fn ($value) => $value === null ? '—' : number_format((float) $value, 2, ',', '.');
@endphp

<div class="mb-4">
    <a href="{{ route('admin.kpi-results.index') }}"
        class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke hasil KPI
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 px-5 py-4">
    <div class="flex items-start justify-between flex-wrap gap-4">
        <div>
            <h3 class="text-[15px] font-bold text-gray-900">Tren Skor Antar Periode</h3>
            <p class="text-[12px] text-gray-500 mt-1">
                Nilai akhir tiap karyawan pada beberapa periode terakhir. Selisih dihitung terhadap
                periode terisi sebelumnya, bukan terhadap kolom di sebelahnya.
            </p>
            @if($isScoped)
            <p class="text-[12px] text-gray-500 mt-1 inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[15px]">filter_alt</span>
                Daftar karyawan dibatasi pada departemen Anda beserta turunannya.
            </p>
            @endif
        </div>
        <form method="GET" action="{{ route('admin.kpi-results.trend') }}" class="flex items-center gap-2">
            <label for="periods" class="text-[12px] font-semibold text-gray-500">Jumlah periode</label>
            <select id="periods" name="periods" onchange="this.form.submit()"
                class="text-[13px] border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                {{-- Nilai berjalan ikut dimasukkan supaya ?periods= di luar daftar tidak tampil salah pilih. --}}
                @foreach(collect([3, 4, 6, 8, 12])->push($limit)->unique()->sort()->values() as $option)
                <option value="{{ $option }}" @selected($limit === $option)>{{ $option }} periode</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

@if($periods->isEmpty())
<div class="bg-white rounded-xl border border-gray-200 p-14 text-center text-gray-400">
    <span class="material-symbols-outlined text-[40px] block mb-2">timeline</span>
    <p class="text-sm font-medium">Belum ada periode penilaian</p>
</div>
@else

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[14px] font-bold text-gray-900">Rata-rata Perusahaan per Periode</h3>
        <p class="text-[12px] text-gray-500 mt-1">
            Angka agregat ini yang dibagikan terbuka ke seluruh karyawan; nilai per individu tetap tertutup.
        </p>
    </div>
    <div class="px-5 py-4 space-y-3">
        @foreach($periods as $period)
        @php
            $company = $companyAverages[$period->id] ?? null;
            $scoped = $scopedAverages[$period->id] ?? null;
        @endphp
        <div>
            <div class="flex items-center justify-between text-[12px] mb-1">
                <span class="font-semibold text-gray-700">
                    {{ $period->name }}
                    @if($period->is_trial)
                    <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded">UJI COBA</span>
                    @endif
                </span>
                <span class="text-gray-500">
                    <strong class="text-gray-900">{{ $fmt($company['average'] ?? null) }}</strong>
                    <span class="text-[11px] text-gray-400">({{ $company['count'] ?? 0 }} karyawan)</span>
                </span>
            </div>
            <div class="h-2.5 w-full rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full bg-indigo-500" style="width: {{ $barWidth($company['average'] ?? null) }}%"></div>
            </div>
            @if($isScoped)
            <div class="flex items-center justify-between text-[11px] text-gray-500 mt-1.5 mb-1">
                <span>Departemen Anda</span>
                <span><strong class="text-gray-700">{{ $fmt($scoped['average'] ?? null) }}</strong> ({{ $scoped['count'] ?? 0 }} karyawan)</span>
            </div>
            <div class="h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $barWidth($scoped['average'] ?? null) }}%"></div>
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">Riwayat per Karyawan ({{ $rows->count() }})</h3>
        <p class="text-[12px] text-gray-500 mt-1">
            Tanda panah menunjukkan perubahan terhadap periode terisi sebelumnya.
        </p>
    </div>

    @if($rows->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">query_stats</span>
        <p class="text-sm font-medium">Belum ada hasil pada periode-periode ini</p>
        <p class="text-xs mt-1">Hasil dihitung saat periode masuk tahap Pemrosesan.</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Karyawan</th>
                    @foreach($periods as $period)
                    <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase whitespace-nowrap">
                        {{ $period->name }}
                    </th>
                    @endforeach
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Rata-rata</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Lembar</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr class="border-b border-gray-50 hover:bg-gray-50/40 transition-all">
                    <td class="py-3.5 px-5">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $row['employee']->full_name }}</div>
                        <div class="text-[11px] text-gray-400">
                            {{ $row['employee']->employee_code }} · {{ $row['employee']->department?->name ?? '—' }}
                        </div>
                    </td>
                    @foreach($periods as $period)
                    @php $cell = $row['cells'][$period->id] ?? null; @endphp
                    <td class="py-3.5 px-4 text-center">
                        @if(($cell['score'] ?? null) === null)
                        <span class="text-[12px] text-gray-300">—</span>
                        @else
                        <a href="{{ route('admin.kpi-results.show', $cell['result']->id) }}"
                            class="text-[13px] font-bold text-gray-900 hover:text-indigo-600 hover:underline">
                            {{ $fmt($cell['score']) }}
                        </a>
                        <span class="ml-1 text-[10px] font-bold
                            {{ in_array($cell['grade'], ['A', 'B']) ? 'text-emerald-600' : ($cell['grade'] === 'C' ? 'text-indigo-600' : 'text-red-600') }}">
                            {{ $cell['grade'] ?? '' }}
                        </span>
                        {{-- Ambang 0,005 supaya pergeseran di bawah presisi tampilan tidak terbaca sebagai naik/turun. --}}
                        @if($cell['delta'] === null)
                        <div class="text-[10px] text-gray-300 mt-0.5">periode awal</div>
                        @elseif($cell['delta'] > 0.005)
                        <div class="text-[10px] text-emerald-600 mt-0.5 flex items-center justify-center gap-0.5">
                            <span class="material-symbols-outlined text-[13px]">arrow_upward</span>
                            {{ number_format($cell['delta'], 2, ',', '.') }}
                        </div>
                        @elseif($cell['delta'] < -0.005)
                        <div class="text-[10px] text-red-600 mt-0.5 flex items-center justify-center gap-0.5">
                            <span class="material-symbols-outlined text-[13px]">arrow_downward</span>
                            {{ number_format(abs($cell['delta']), 2, ',', '.') }}
                        </div>
                        @else
                        <div class="text-[10px] text-gray-400 mt-0.5 flex items-center justify-center gap-0.5">
                            <span class="material-symbols-outlined text-[13px]">remove</span> tetap
                        </div>
                        @endif
                        @endif
                    </td>
                    @endforeach
                    <td class="py-3.5 px-5 text-center">
                        <div class="text-[13px] font-bold text-gray-900">{{ $fmt($row['average']) }}</div>
                        <div class="text-[10px] text-gray-400">{{ $row['filled'] }} dari {{ $periods->count() }} periode</div>
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        @if($row['latestResult'])
                        <a href="{{ route('admin.kpi-results.pdf', $row['latestResult']->id) }}"
                            title="Unduh lembar hasil periode terakhir"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-100 rounded-lg hover:bg-indigo-100">
                            <span class="material-symbols-outlined text-[15px]">picture_as_pdf</span> PDF
                        </a>
                        @else
                        <span class="text-[12px] text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endif
@endsection

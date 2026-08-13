@extends('admin.layouts.app')
@section('title', 'Periode Penilaian')

@section('content')
@if($problems)
<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
    <p class="text-[13px] font-bold text-amber-800 flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[18px]">warning</span>
        Master KPI belum siap dibekukan
    </p>
    <ul class="mt-2 ml-6 list-disc text-[12px] text-amber-700 space-y-0.5">
        @foreach($problems as $problem)
        <li>{{ $problem }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">
            <span class="material-symbols-outlined text-[18px] align-text-bottom">add_circle</span> Buat Periode Baru
        </h3>
    </div>
    <form action="{{ route('admin.kpi-periods.store') }}" method="POST" class="px-5 py-4">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
            <div class="md:col-span-3">
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Nama periode</label>
                <input type="text" name="name" required maxlength="100" placeholder="cth: Semester I 2026"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Kinerja dinilai dari</label>
                <input type="date" name="start_date" required class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Sampai</label>
                <input type="date" name="end_date" required class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-[13px] text-gray-700 pb-2.5">
                    <input type="checkbox" name="is_trial" value="1" checked class="w-4 h-4 rounded border-gray-300 text-indigo-600">
                    Periode uji coba
                </label>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Pengisian penilai — mulai</label>
                <input type="date" name="fill_start" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Pengisian penilai — selesai</label>
                <input type="date" name="fill_end" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-xl shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
                    Buat Periode
                </button>
            </div>
        </div>
        <p class="text-[11px] text-gray-500 mt-3">
            Periode uji coba tetap dihitung tapi ditandai tidak berkonsekuensi. Kerangka menyarankan
            periode pertama dijalankan tanpa dikaitkan ke bonus atau promosi.
        </p>
    </form>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">Daftar Periode</h3>
    </div>

    @if($periods->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">event_repeat</span>
        <p class="text-sm font-medium">Belum ada periode penilaian</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Periode</th>
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Rentang Kinerja</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Status</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Indikator Beku</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($periods as $period)
                <tr class="border-b border-gray-50 hover:bg-gray-50/40 transition-all">
                    <td class="py-3.5 px-5">
                        <a href="{{ route('admin.kpi-periods.show', $period->id) }}" class="text-[13px] font-semibold text-indigo-600 hover:underline">
                            {{ $period->name }}
                        </a>
                        @if($period->is_trial)
                        <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded">UJI COBA</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-5 text-[12px] text-gray-600">
                        {{ $period->start_date?->format('d/m/Y') }} – {{ $period->end_date?->format('d/m/Y') }}
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg
                            {{ $period->isFinal() ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : ($period->isDraft() ? 'text-gray-600 bg-gray-100 border border-gray-200' : 'text-indigo-700 bg-indigo-50 border border-indigo-200') }}">
                            {{ $period->statusLabel() }}
                        </span>
                    </td>
                    <td class="py-3.5 px-5 text-center text-[13px] text-gray-500">
                        {{ $period->indicator_snapshots_count ?: '—' }}
                    </td>
                    <td class="py-3.5 px-5">
                        <div class="flex items-center justify-center gap-2">
                            @if($period->isDraft())
                            <form action="{{ route('admin.kpi-periods.open', $period->id) }}" method="POST"
                                data-confirm="Buka periode &quot;{{ $period->name }}&quot;? Bobot dan indikator akan dibekukan dan tidak bisa diubah lagi untuk periode ini.">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-[11px] font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-all cursor-pointer">
                                    Buka Periode
                                </button>
                            </form>
                            <form action="{{ route('admin.kpi-periods.destroy', $period->id) }}" method="POST"
                                data-confirm="Hapus periode &quot;{{ $period->name }}&quot;?">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-2 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg cursor-pointer">
                                    <span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span>
                                </button>
                            </form>
                            @else
                            <a href="{{ route('admin.kpi-periods.show', $period->id) }}"
                                class="px-3 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">
                                Kelola
                            </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection

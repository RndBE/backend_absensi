@extends('admin.layouts.app')
@section('title', 'Sanggahan Hasil KPI')

@section('content')
@if($periods->isEmpty())
<div class="bg-white rounded-xl border border-gray-200 p-14 text-center text-gray-400">
    <span class="material-symbols-outlined text-[40px] block mb-2">gavel</span>
    <p class="text-sm font-medium">Belum ada periode penilaian</p>
</div>
@else

<div class="flex items-center gap-2 mb-4 flex-wrap">
    @foreach($periods as $p)
    <a href="{{ route('admin.kpi-appeals.index', ['period' => $p->id]) }}"
        class="px-4 py-2 text-[13px] font-semibold rounded-lg border transition-all {{ $period?->id === $p->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
        {{ $p->name }}
    </a>
    @endforeach
</div>

<div class="mb-4 rounded-xl border {{ $isOpen ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200 bg-gray-50' }} px-5 py-4">
    <p class="text-[13px] font-bold {{ $isOpen ? 'text-indigo-800' : 'text-gray-700' }} flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[18px]">schedule</span>
        @if($deadline === null)
        Periode belum difinalkan — tenggat sanggah belum berjalan
        @elseif($isOpen)
        Sanggahan dibuka sampai {{ $deadline->format('d/m/Y') }}
        @else
        Tenggat sanggah sudah lewat pada {{ $deadline->format('d/m/Y') }}
        @endif
    </p>
    <p class="text-[12px] {{ $isOpen ? 'text-indigo-700' : 'text-gray-500' }} mt-1.5">
        Hak sanggah berlaku 7 hari kerja setelah hasil diterima. Keputusan diambil atasan dua tingkat
        di atas, paling lama 14 hari kerja, dan bersifat final untuk periode tersebut.
    </p>
</div>

@if($isOpen)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">
            <span class="material-symbols-outlined text-[18px] align-text-bottom">post_add</span> Catat Sanggahan
        </h3>
    </div>

    @if($candidates->isEmpty())
    <div class="px-5 py-8 text-center text-[13px] text-gray-400">
        Seluruh karyawan dengan hasil final sudah tercatat sanggahannya.
    </div>
    @else
    <form action="{{ route('admin.kpi-appeals.store') }}" method="POST" class="px-5 py-4">
        @csrf
        <input type="hidden" name="kpi_period_id" value="{{ $period->id }}">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Karyawan</label>
                <select name="employee_id" required
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
                    <option value="">— pilih karyawan —</option>
                    @foreach($candidates as $candidate)
                    <option value="{{ $candidate->employee->id }}" @selected(old('employee_id') == $candidate->employee->id)>
                        {{ $candidate->employee->full_name }} ({{ $candidate->employee->employee_code }})
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Alasan sanggahan</label>
                <textarea name="reason" rows="3" required minlength="10" maxlength="2000"
                    placeholder="Tuliskan bagian hasil yang disanggah beserta alasannya"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">{{ old('reason') }}</textarea>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 mt-3">
            <p class="text-[11px] text-gray-500">
                Sanggahan ditembuskan ke atasan langsung; keputusan diambil atasan dua tingkat.
            </p>
            <button type="submit" class="px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-xl shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer shrink-0">
                Catat Sanggahan
            </button>
        </div>
    </form>
    @endif
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">Daftar Sanggahan ({{ $appeals->count() }})</h3>
    </div>

    @if($appeals->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">gavel</span>
        <p class="text-sm font-medium">Belum ada sanggahan pada periode ini</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Karyawan</th>
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Alasan</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Diajukan</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Batas Putusan</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($appeals as $appeal)
                <tr class="border-b border-gray-50 {{ $appeal->isDecided() ? '' : 'bg-amber-50/30' }}">
                    <td class="py-3.5 px-5 align-top">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $appeal->employee?->full_name ?? '—' }}</div>
                        <div class="text-[11px] text-gray-400">{{ $appeal->employee?->department?->name ?? '—' }}</div>
                    </td>
                    <td class="py-3.5 px-5 align-top max-w-md">
                        <p class="text-[12px] text-gray-600 whitespace-pre-line">{{ $appeal->reason }}</p>
                        @if($appeal->isDecided())
                        <div class="mt-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                            <p class="text-[11px] font-bold text-gray-600">
                                Catatan keputusan — {{ $appeal->decider?->full_name ?? 'tidak tercatat' }}
                                ({{ $appeal->decided_at?->format('d/m/Y') }})
                            </p>
                            <p class="text-[12px] text-gray-600 mt-1 whitespace-pre-line">{{ $appeal->decision_note }}</p>
                        </div>
                        @endif
                    </td>
                    <td class="py-3.5 px-5 text-center text-[12px] text-gray-600 align-top">
                        {{ $appeal->submitted_at?->format('d/m/Y') ?? '—' }}
                    </td>
                    <td class="py-3.5 px-5 text-center text-[12px] align-top {{ $appeal->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                        {{ $appeal->decisionDeadline()?->format('d/m/Y') ?? '—' }}
                        @if($appeal->isOverdue())
                        <div class="text-[10px] font-bold text-red-600">lewat batas</div>
                        @endif
                    </td>
                    <td class="py-3.5 px-5 text-center align-top">
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg
                            {{ $appeal->isAccepted() ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : ($appeal->isDecided() ? 'text-red-700 bg-red-50 border border-red-200' : 'text-amber-700 bg-amber-50 border border-amber-200') }}">
                            {{ $appeal->statusLabel() }}
                        </span>
                    </td>
                </tr>

                @if(! $appeal->isDecided())
                <tr class="border-b border-gray-50 bg-amber-50/30">
                    <td colspan="5" class="px-5 pb-4">
                        <form action="{{ route('admin.kpi-appeals.decide', $appeal->id) }}" method="POST"
                            class="flex flex-col md:flex-row md:items-end gap-3">
                            @csrf
                            <div class="flex-1">
                                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">
                                    Catatan keputusan (wajib)
                                </label>
                                <textarea name="decision_note" rows="2" required minlength="10" maxlength="2000"
                                    placeholder="Dasar keputusan: bukti yang ditinjau ulang dan hasilnya"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500"></textarea>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="submit" name="status" value="{{ \App\Models\KpiAppeal::STATUS_ACCEPTED }}"
                                    class="px-4 py-2.5 text-[12px] font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 transition-all cursor-pointer">
                                    Terima
                                </button>
                                <button type="submit" name="status" value="{{ \App\Models\KpiAppeal::STATUS_REJECTED }}"
                                    class="px-4 py-2.5 text-[12px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 transition-all cursor-pointer">
                                    Tolak
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endif
@endsection

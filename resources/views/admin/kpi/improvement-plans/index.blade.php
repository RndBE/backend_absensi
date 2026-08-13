@extends('admin.layouts.app')
@section('title', 'Rencana Perbaikan')

@php $statusLabels = \App\Models\KpiImprovementPlan::STATUS_LABELS; @endphp

@section('content')
@if($periods->isEmpty())
<div class="bg-white rounded-xl border border-gray-200 p-14 text-center text-gray-400">
    <span class="material-symbols-outlined text-[40px] block mb-2">trending_up</span>
    <p class="text-sm font-medium">Belum ada periode penilaian</p>
</div>
@else

<div class="flex items-center gap-2 mb-4 flex-wrap">
    @foreach($periods as $p)
    <a href="{{ route('admin.kpi-improvement-plans.index', ['period' => $p->id]) }}"
        class="px-4 py-2 text-[13px] font-semibold rounded-lg border transition-all {{ $period?->id === $p->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
        {{ $p->name }}
    </a>
    @endforeach
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
        <p class="text-[11px] font-bold text-gray-500 uppercase">Total Rencana</p>
        <p class="text-[22px] font-bold text-gray-900 leading-tight mt-1">{{ $summary['total'] }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
        <p class="text-[11px] font-bold text-gray-500 uppercase">Lewat Tenggat</p>
        <p class="text-[22px] font-bold {{ $summary['overdue'] > 0 ? 'text-red-600' : 'text-gray-900' }} leading-tight mt-1">
            {{ $summary['overdue'] }}
        </p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
        <p class="text-[11px] font-bold text-gray-500 uppercase">Selesai</p>
        <p class="text-[22px] font-bold text-emerald-600 leading-tight mt-1">{{ $summary['done'] }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h3 class="text-[15px] font-bold text-gray-900">
                <span class="material-symbols-outlined text-[18px] align-text-bottom">trending_up</span>
                Rencana Perbaikan — {{ $period?->name }}
            </h3>
            <p class="text-[12px] text-gray-500 mt-1">
                Pemicu wajib: nilai akhir individu &lt; 3,0 (2 minggu), &lt; 2,0 (PIP formal, 1 minggu,
                evaluasi 3 bulan), dan skor silang divisi &lt; 3,0 (2 minggu).
            </p>
        </div>
        @if($period)
        <form action="{{ route('admin.kpi-improvement-plans.generate', $period->id) }}" method="POST"
            data-confirm="Jalankan pemicu tindak lanjut untuk periode &quot;{{ $period->name }}&quot;? Rencana yang sudah ada tidak akan ditimpa.">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">bolt</span> Jalankan Pemicu
            </button>
        </form>
        @endif
    </div>

    @if($plans->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">check_circle</span>
        <p class="text-sm font-medium">Belum ada rencana perbaikan</p>
        <p class="text-xs mt-1">Klik "Jalankan Pemicu" setelah nilai akhir periode dihitung.</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Subjek</th>
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Pemicu</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Skor</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Tenggat</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Tinjauan</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Status</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($plans as $plan)
                <tr class="border-b border-gray-50 hover:bg-gray-50/40 transition-all">
                    <td class="py-3.5 px-5">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $plan->subjectName() }}</div>
                        <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded {{ $plan->isDivision() ? 'text-indigo-700 bg-indigo-50 border border-indigo-200' : 'text-gray-600 bg-gray-100 border border-gray-200' }}">
                            {{ $plan->subjectLabel() }}
                        </span>
                    </td>
                    <td class="py-3.5 px-5 max-w-sm">
                        <p class="text-[12px] text-gray-600">{{ $plan->trigger_reason }}</p>
                        @if($plan->plan_text)
                        <p class="text-[11px] text-gray-500 mt-1.5 whitespace-pre-line border-l-2 border-indigo-200 pl-2">{{ $plan->plan_text }}</p>
                        @else
                        <p class="text-[11px] text-amber-600 mt-1.5">Isi rencana belum ditulis.</p>
                        @endif
                    </td>
                    <td class="py-3.5 px-5 text-center text-[13px] font-bold text-gray-900">
                        {{ $plan->trigger_score === null ? '—' : number_format((float) $plan->trigger_score, 2, ',', '.') }}
                    </td>
                    <td class="py-3.5 px-5 text-center text-[12px] {{ $plan->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                        {{ $plan->due_date?->format('d/m/Y') ?? '—' }}
                    </td>
                    <td class="py-3.5 px-5 text-center text-[12px] text-gray-600">
                        {{ $plan->review_date?->format('d/m/Y') ?? '—' }}
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        @php $effective = $plan->effectiveStatus(); @endphp
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg
                            @if($effective === \App\Models\KpiImprovementPlan::STATUS_DONE) text-emerald-700 bg-emerald-50 border border-emerald-200
                            @elseif($effective === \App\Models\KpiImprovementPlan::STATUS_OVERDUE) text-red-700 bg-red-50 border border-red-200
                            @elseif($effective === \App\Models\KpiImprovementPlan::STATUS_IN_PROGRESS) text-indigo-700 bg-indigo-50 border border-indigo-200
                            @else text-gray-600 bg-gray-100 border border-gray-200 @endif">
                            {{ $plan->statusLabel() }}
                        </span>
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        <button type="button"
                            onclick="openPlanModal({{ $plan->id }}, @js($plan->subjectName()), @js($plan->plan_text), @js($plan->due_date?->format('Y-m-d')), @js($plan->review_date?->format('Y-m-d')), @js($plan->status))"
                            class="px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">edit_note</span> Kelola
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- ═══════════════════════════════════════════════════ --}}
{{-- MODAL: KELOLA RENCANA PERBAIKAN                     --}}
{{-- ═══════════════════════════════════════════════════ --}}
<div id="planModal" class="fixed inset-0 z-[100] items-center justify-center p-4 hidden" style="display:none">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closePlanModal()"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg transform transition-all duration-300 scale-95 opacity-0" id="planModalContent">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-indigo-500">edit_note</span>
                <span id="planModalTitle">Rencana Perbaikan</span>
            </h3>
            <button onclick="closePlanModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-all cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <form id="planForm" method="POST">
            @csrf
            @method('PUT')

            <div class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Isi rencana perbaikan</label>
                    <textarea name="plan_text" id="planText" rows="6" maxlength="5000"
                        placeholder="Apa yang diperbaiki, oleh siapa, dengan ukuran keberhasilan apa"
                        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 transition-all"></textarea>
                    <p class="text-[11px] text-gray-500 mt-1.5">
                        Untuk rencana divisi, fokusnya perbaikan proses — bukan mencari siapa yang salah.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Tenggat</label>
                        <input type="date" name="due_date" id="planDueDate"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Tanggal tinjauan</label>
                        <input type="date" name="review_date" id="planReviewDate"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Status</label>
                    <select name="status" id="planStatus"
                        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
                        @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-2xl">
                <button type="button" onclick="closePlanModal()" class="px-4 py-2.5 text-[13px] font-semibold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-all cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-xl shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">save</span> Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if($periods->isNotEmpty())
<script>
    const planModal        = document.getElementById('planModal');
    const planModalContent = document.getElementById('planModalContent');
    const planForm         = document.getElementById('planForm');
    const planModalTitle   = document.getElementById('planModalTitle');
    const planText         = document.getElementById('planText');
    const planDueDate      = document.getElementById('planDueDate');
    const planReviewDate   = document.getElementById('planReviewDate');
    const planStatus       = document.getElementById('planStatus');

    const planUrlBase = "{{ url('admin/kpi/improvement-plans') }}";

    function openPlanModal(id, subject, text, dueDate, reviewDate, status) {
        planForm.action        = planUrlBase + '/' + id;
        planModalTitle.textContent = 'Rencana Perbaikan — ' + subject;
        planText.value         = text || '';
        planDueDate.value      = dueDate || '';
        planReviewDate.value   = reviewDate || '';
        planStatus.value       = status;

        planModal.style.display = 'flex';
        requestAnimationFrame(() => {
            planModalContent.classList.remove('scale-95', 'opacity-0');
            planModalContent.classList.add('scale-100', 'opacity-100');
        });
        setTimeout(() => planText.focus(), 150);
    }

    function closePlanModal() {
        planModalContent.classList.remove('scale-100', 'opacity-100');
        planModalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { planModal.style.display = 'none'; }, 200);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && planModal.style.display !== 'none') closePlanModal();
    });
</script>
@endif
@endpush

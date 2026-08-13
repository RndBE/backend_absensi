@extends('admin.layouts.app')
@section('title', 'Penilaian Silang Antar Divisi')

@section('content')
@if($periods->isEmpty())
<div class="bg-white rounded-xl border border-gray-200 p-14 text-center text-gray-400">
    <span class="material-symbols-outlined text-[40px] block mb-2">swap_horiz</span>
    <p class="text-sm font-medium">Belum ada periode penilaian yang dibuka</p>
</div>
@else

<div class="flex items-center gap-2 mb-4 flex-wrap">
    @foreach($periods as $p)
    <a href="{{ route('admin.kpi-cross.index', ['period' => $p->id]) }}"
        class="px-4 py-2 text-[13px] font-semibold rounded-lg border transition-all {{ $period?->id === $p->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
        {{ $p->name }}
    </a>
    @endforeach
</div>

@if(! $assignment)
<div class="bg-white rounded-xl border border-gray-200 p-14 text-center text-gray-400">
    <span class="material-symbols-outlined text-[40px] block mb-2">person_off</span>
    <p class="text-sm font-medium">Anda bukan penilai silang resmi pada periode ini</p>
    <p class="text-xs mt-1">Penilai ditetapkan HRD di awal periode berdasarkan intensitas interaksi kerja.</p>
</div>
@else

{{-- Aturan anonimitas disampaikan terang-terangan (Bab 7.7): orang lebih hati-hati menulis
     kalau tahu ada jejaknya, tapi tetap merasa aman dari konfrontasi langsung. --}}
<div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4">
    <p class="text-[13px] font-bold text-indigo-900 flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[18px]">shield_person</span>
        Bagaimana jawaban Anda diperlakukan
    </p>
    <ul class="mt-2 ml-6 list-disc text-[12px] text-indigo-800 space-y-1">
        <li>Nama Anda <strong>tidak ditampilkan</strong> kepada pihak yang dinilai — mereka hanya melihat skor rata-rata dan komentar tanpa identitas pengirim.</li>
        <li>Identitas Anda <strong>tercatat di sistem</strong> dan dapat diakses HRD untuk menelusuri penyalahgunaan.</li>
        <li>Yang dinilai adalah <strong>pengalaman kerja nyata selama 6 bulan terakhir</strong>, bukan kesan pribadi atau kejadian terbaru saja.</li>
        <li>Hasilnya bahan pertimbangan, bukan vonis otomatis — atasan langsung masih bisa mengoreksi dengan alasan tertulis.</li>
    </ul>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">
            <span class="material-symbols-outlined text-[18px] align-text-bottom">apartment</span>
            Lapis A — Menilai Divisi Mitra ({{ $doneA->count() }}/{{ $partners->count() }})
        </h3>
        <p class="text-[12px] text-gray-500 mt-1">Menilai divisi sebagai unit. Wajib diisi seluruhnya.</p>
    </div>

    @if($partners->isEmpty())
    <div class="py-10 text-center text-gray-400 text-[13px]">
        Divisi Anda belum punya mitra pada matriks relasi kerja. Hubungi HRD.
    </div>
    @else
    <div class="divide-y divide-gray-50">
        @foreach($partners as $partner)
        @php $done = $doneA->get($partner->id); @endphp
        <div class="px-5 py-3.5 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-400 text-white flex items-center justify-center text-[11px] font-bold shrink-0">
                    {{ strtoupper(substr($partner->kpi_code ?: $partner->name, 0, 3)) }}
                </div>
                <div>
                    <p class="text-[13px] font-semibold text-gray-800">{{ $partner->name }}</p>
                    @if($partner->is_shared_service)
                    <span class="text-[10px] font-bold text-gray-500">divisi layanan umum</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($done?->submitted_at)
                <span class="px-2.5 py-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg">
                    Terisi {{ $done->submitted_at->format('d/m/Y') }}
                </span>
                @endif
                <a href="{{ route('admin.kpi-cross.division.edit', [$period->id, $partner->id]) }}"
                    class="px-3 py-1.5 text-[11px] font-semibold {{ $done ? 'text-gray-600 bg-gray-50 border-gray-200' : 'text-indigo-600 bg-indigo-50 border-indigo-200' }} border rounded-lg hover:opacity-80 transition-all">
                    {{ $done ? 'Ubah' : 'Isi' }}
                </a>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>

@if($assignment->can_assess_individual)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">
            <span class="material-symbols-outlined text-[18px] align-text-bottom">person</span>
            Lapis B — Menilai Individu ({{ $doneB->count() }}/{{ $individuals->count() }})
        </h3>
        <p class="text-[12px] text-gray-500 mt-1">
            Hanya orang yang benar-benar Anda ajak bekerja. Kalau tidak pernah berinteraksi langsung, lewati saja.
        </p>
    </div>

    @if($individuals->isEmpty())
    <div class="py-10 text-center text-gray-400 text-[13px]">Tidak ada individu lintas fungsi untuk dinilai.</div>
    @else
    <div class="divide-y divide-gray-50">
        @foreach($individuals as $target)
        @php $done = $doneB->get($target->id); @endphp
        <div class="px-5 py-3.5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[13px] font-semibold text-gray-800">{{ $target->full_name }}</p>
                <p class="text-[11px] text-gray-400">
                    {{ $target->position ?: '—' }} · {{ $target->department?->name ?? '—' }}
                    @if($target->kpiLevel)<span class="ml-1 px-1.5 py-0.5 bg-gray-100 rounded font-bold">{{ $target->kpiLevel->code }}</span>@endif
                    @if($target->is_cross_functional)<span class="ml-1 text-amber-600 font-semibold">lintas fungsi</span>@endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if($done?->submitted_at)
                <span class="px-2.5 py-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg">Terisi</span>
                @endif
                <a href="{{ route('admin.kpi-cross.individual.edit', [$period->id, $target->id]) }}"
                    class="px-3 py-1.5 text-[11px] font-semibold {{ $done ? 'text-gray-600 bg-gray-50 border-gray-200' : 'text-indigo-600 bg-indigo-50 border-indigo-200' }} border rounded-lg hover:opacity-80 transition-all">
                    {{ $done ? 'Ubah' : 'Isi' }}
                </a>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endif

@endif
@endif
@endsection

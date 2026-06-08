@extends('admin.layouts.app')
@section('title', 'Monitor Approval')

@section('content')
<div class="mb-5">
    <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-indigo-500 text-[22px]">monitoring</span>
        <div>
            <h2 class="text-[16px] font-bold text-gray-900">Monitor Approval</h2>
            <p class="text-[12px] text-gray-400">Pantau semua pengajuan & alur persetujuan seluruh karyawan</p>
        </div>
    </div>
</div>

{{-- Filter Bar --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <form method="GET" class="p-4 flex flex-wrap items-end gap-3">
        {{-- Search --}}
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[12px] font-semibold text-gray-600 mb-1">Cari Karyawan</label>
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Nama atau kode karyawan..."
                   class="w-full px-3.5 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400">
        </div>

        {{-- Type Filter --}}
        <div>
            <label class="block text-[12px] font-semibold text-gray-600 mb-1">Jenis Pengajuan</label>
            <div class="flex gap-1.5 flex-wrap">
                @php
                    $typeOptions = array_merge(['all' => 'Semua'], array_map(fn($m) => $m['label'], $typeMap));
                    $typePending = array_merge(['all' => $summary['all']], array_map(fn($k) => $summary[$k], array_keys($typeMap)));
                @endphp
                @foreach($typeOptions as $val => $label)
                    @php $cnt = $summary[$val] ?? 0; @endphp
                    <a href="{{ request()->fullUrlWithQuery(['type' => $val, 'status' => $filterStatus, 'search' => $search, 'page' => 1]) }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-[12px] font-semibold transition-all
                              {{ $filterType === $val ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        {{ $label }}
                        @if($cnt > 0)
                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full text-[10px] font-bold
                                         {{ $filterType === $val ? 'bg-white/30 text-white' : 'bg-red-100 text-red-600' }}">{{ $cnt }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Status Filter --}}
        <div>
            <label class="block text-[12px] font-semibold text-gray-600 mb-1">Status</label>
            <div class="flex gap-1.5">
                @foreach(['active' => 'Aktif (Pending)', 'done' => 'Selesai', 'all' => 'Semua'] as $val => $label)
                    <a href="{{ request()->fullUrlWithQuery(['status' => $val, 'type' => $filterType, 'search' => $search, 'page' => 1]) }}"
                       class="px-3 py-1.5 rounded-lg text-[12px] font-semibold transition-all
                              {{ $filterStatus === $val ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        @if($search)
            <a href="{{ route('admin.monitor-approvals.index', ['type' => $filterType, 'status' => $filterStatus]) }}"
               class="px-3 py-2 text-xs font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all self-end">
                ✕ Reset
            </a>
        @endif
    </form>
</div>

{{-- Table --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
        <span class="text-[13.5px] font-bold text-gray-800">
            {{ $requests->total() }} pengajuan ditemukan
        </span>
        @if($filterStatus === 'active')
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Menunggu Tindakan
            </span>
        @endif
    </div>

    @if($requests->isEmpty())
        <div class="py-16 text-center">
            <span class="material-symbols-outlined text-[48px] text-gray-300">task_alt</span>
            <p class="text-gray-400 text-sm mt-2">Tidak ada pengajuan ditemukan</p>
        </div>
    @else
        <div class="divide-y divide-gray-100">
            @foreach($requests as $row)
                @php
                    $item    = $row['item'];
                    $chain   = $row['chain'];   // EmployeeApprover collection
                    $logs    = $item->approvalLogs; // ApprovalLog collection
                    $emp     = $item->employee;

                    // Status styling
                    $statusColor = match($item->status) {
                        'pending'   => 'bg-amber-100 text-amber-800',
                        'in_review' => 'bg-blue-100 text-blue-800',
                        'approved'  => 'bg-emerald-100 text-emerald-800',
                        'rejected'  => 'bg-red-100 text-red-800',
                        default     => 'bg-gray-100 text-gray-600',
                    };
                    $statusLabel = match($item->status) {
                        'pending'   => 'Menunggu',
                        'in_review' => 'Diproses',
                        'approved'  => 'Disetujui',
                        'rejected'  => 'Ditolak',
                        default     => ucfirst($item->status),
                    };

                    // Type styling
                    $typeColor = match($row['type']) {
                        'leave'      => 'bg-purple-100 text-purple-700',
                        'overtime'   => 'bg-orange-100 text-orange-700',
                        'attendance' => 'bg-cyan-100 text-cyan-700',
                        'budget'     => 'bg-green-100 text-green-700',
                        'travel'     => 'bg-indigo-100 text-indigo-700',
                        default      => 'bg-gray-100 text-gray-600',
                    };

                    $formatDurationValue = function ($value) {
                        $number = (float) $value;
                        if (floor($number) === $number) {
                            return (string) (int) $number;
                        }

                        return rtrim(rtrim(number_format($number, 1, ',', '.'), '0'), ',');
                    };

                    // Build chain steps with log info
                    $chainSteps = [];
                    if ($chain->isNotEmpty()) {
                        foreach ($chain as $step) {
                            $log = $logs->firstWhere('step_order', $step->step_order);
                            $chainSteps[] = [
                                'step'     => $step->step_order,
                                'approver' => $step->approver,
                                'log'      => $log,
                                'state'    => $log
                                    ? ($log->action === 'approved' ? 'approved' : 'rejected')
                                    : ($item->current_step == $step->step_order && in_array($item->status, ['pending','in_review']) ? 'current' : 'waiting'),
                            ];
                        }
                    }

                    // Summary info per type
                    $summaryLine = match($row['type']) {
                        'leave'      => ($item->leaveType->name ?? 'Cuti') . ' · ' . $item->start_date->format('d/m/Y') . ($item->total_days ? ' · ' . $formatDurationValue($item->total_days) . ' hari' : ''),
                        'overtime'   => $item->date->format('d/m/Y') . ' · ' . ($item->overtime_type === 'holiday' ? 'Hari Libur' : 'Hari Kerja') . ' · ' . $formatDurationValue(($item->total_duration ?? 0) / 60) . ' jam',
                        'attendance' => $item->date->format('d/m/Y') . ' · In: ' . ($item->clock_in ?? '-') . ' Out: ' . ($item->clock_out ?? '-'),
                        'budget'     => ($item->title ?? 'Anggaran') . ' · ' . 'Rp ' . number_format($item->total_amount ?? 0, 0, ',', '.'),
                        'travel'     => ($item->destination_city ?? '-') . ' · ' . ($item->departure_date?->format('d/m') ?? '-') . ' s/d ' . ($item->return_date?->format('d/m/Y') ?? '-'),
                        default      => '',
                    };
                @endphp

                <div class="px-5 py-4 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start gap-4">
                        {{-- Avatar --}}
                        <div class="shrink-0 mt-0.5">
                            @if($emp?->photo)
                                <img src="{{ asset('storage/' . $emp->photo) }}" class="w-9 h-9 rounded-full object-cover border border-gray-200">
                            @else
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-[13px] font-bold">
                                    {{ substr($emp?->full_name ?? '?', 0, 1) }}
                                </div>
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            {{-- Top row --}}
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="text-[13.5px] font-bold text-gray-900">{{ $emp?->full_name ?? '-' }}</span>
                                <span class="text-[11px] text-gray-400">{{ $emp?->employee_code }}</span>
                                <span class="text-gray-300">·</span>
                                <span class="text-[11px] text-gray-500">{{ $emp?->department?->name ?? '-' }}</span>
                                <span class="text-gray-300">·</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold {{ $typeColor }}">{{ $row['type_label'] }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold {{ $statusColor }}">{{ $statusLabel }}</span>
                                <span class="ml-auto text-[11px] text-gray-400">{{ $item->created_at->format('d/m/Y H:i') }}</span>
                            </div>

                            {{-- Summary --}}
                            <div class="text-[12px] text-gray-600 mb-3">{{ $summaryLine }}</div>

                            {{-- Approval Chain --}}
                            @if($chainSteps)
                                <div class="flex items-center gap-0 flex-wrap">
                                    @foreach($chainSteps as $i => $step)
                                        @php
                                            $dotColor = match($step['state']) {
                                                'approved' => 'bg-emerald-500 border-emerald-500',
                                                'rejected' => 'bg-red-500 border-red-500',
                                                'current'  => 'bg-amber-400 border-amber-400 ring-2 ring-amber-200',
                                                default    => 'bg-white border-gray-300',
                                            };
                                            $textColor = match($step['state']) {
                                                'approved' => 'text-emerald-700',
                                                'rejected' => 'text-red-700',
                                                'current'  => 'text-amber-700',
                                                default    => 'text-gray-400',
                                            };
                                            $icon = match($step['state']) {
                                                'approved' => 'check',
                                                'rejected' => 'close',
                                                'current'  => '!',
                                                default    => (string)$step['step'],
                                            };
                                        @endphp

                                        {{-- Connector line --}}
                                        @if($i > 0)
                                            <div class="w-6 h-px {{ in_array($chainSteps[$i-1]['state'], ['approved']) ? 'bg-emerald-400' : 'bg-gray-200' }}"></div>
                                        @endif

                                        {{-- Step dot --}}
                                        <div class="relative group flex flex-col items-center">
                                            <div class="w-7 h-7 rounded-full border-2 {{ $dotColor }} flex items-center justify-center text-[10px] font-bold {{ $step['state'] === 'current' ? 'animate-pulse' : '' }}
                                                        {{ in_array($step['state'], ['approved','rejected']) ? 'text-white' : $textColor }}">
                                                @if($step['state'] === 'approved')
                                                    <span class="material-symbols-outlined text-[14px]">check</span>
                                                @elseif($step['state'] === 'rejected')
                                                    <span class="material-symbols-outlined text-[14px]">close</span>
                                                @else
                                                    {{ $step['step'] }}
                                                @endif
                                            </div>

                                            {{-- Tooltip --}}
                                            <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 hidden group-hover:block z-10 pointer-events-none">
                                                <div class="bg-gray-900 text-white text-[11px] rounded-lg px-2.5 py-1.5 whitespace-nowrap shadow-lg">
                                                    <div class="font-semibold">{{ $step['approver']?->full_name ?? 'Step ' . $step['step'] }}</div>
                                                    <div class="text-gray-300 text-[10px]">{{ $step['approver']?->position ?? '' }}</div>
                                                    @if($step['log'])
                                                        <div class="text-[10px] mt-0.5 {{ $step['state'] === 'approved' ? 'text-emerald-400' : 'text-red-400' }}">
                                                            {{ $step['state'] === 'approved' ? 'Disetujui' : 'Ditolak' }} · {{ $step['log']->created_at->format('d/m/Y H:i') }}
                                                        </div>
                                                        @if($step['log']->notes)
                                                            <div class="text-gray-300 text-[10px] max-w-[200px] truncate">"{{ $step['log']->notes }}"</div>
                                                        @endif
                                                    @elseif($step['state'] === 'current')
                                                        <div class="text-amber-400 text-[10px] mt-0.5">Menunggu persetujuan</div>
                                                    @else
                                                        <div class="text-gray-400 text-[10px] mt-0.5">Belum diproses</div>
                                                    @endif
                                                </div>
                                                <div class="w-2 h-2 bg-gray-900 rotate-45 mx-auto -mt-1"></div>
                                            </div>
                                        </div>
                                    @endforeach

                                    {{-- Final state indicator --}}
                                    @if(in_array($item->status, ['approved', 'rejected']))
                                        <div class="w-6 h-px {{ $item->status === 'approved' ? 'bg-emerald-400' : 'bg-red-300' }}"></div>
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center
                                                    {{ $item->status === 'approved' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600' }}">
                                            <span class="material-symbols-outlined text-[15px]">{{ $item->status === 'approved' ? 'task_alt' : 'cancel' }}</span>
                                        </div>
                                    @endif

                                    {{-- Step label --}}
                                    <span class="ml-3 text-[11px] text-gray-400">
                                        @if(in_array($item->status, ['pending','in_review']))
                                            Step {{ $item->current_step }} / {{ count($chainSteps) }}
                                        @elseif($item->status === 'approved')
                                            <span class="text-emerald-600 font-semibold">Selesai disetujui</span>
                                        @else
                                            <span class="text-red-600 font-semibold">Ditolak</span>
                                        @endif
                                    </span>
                                </div>
                            @else
                                {{-- No chain defined --}}
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] text-gray-400 italic">Belum ada rantai persetujuan dikonfigurasi</span>
                                    @if(in_array($item->status, ['approved','rejected']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold {{ $statusColor }}">
                                            {{ $statusLabel }} (langsung)
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($requests->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">
                {{ $requests->links() }}
            </div>
        @endif
    @endif
</div>
@endsection

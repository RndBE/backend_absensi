{{--
    Cue "LHP belum dibuat" untuk pengaju.
    Tampil jika: anggaran sudah cair (approved/paid), punya tanggal pulang,
    dan employee ini belum membuat LHP-nya ($budgetRequest->has_lhp == false).
    Butuh: $budgetRequest dengan withExists('travelReport as has_lhp') + employee.company_id.
--}}
@if(in_array($budgetRequest->status, ['approved', 'paid']) && $budgetRequest->return_date && ! ($budgetRequest->has_lhp ?? false))
    @php
        $deadline = $budgetRequest->lhpDeadlineDate();
        $today = \Illuminate\Support\Carbon::today();
        $isLate = $deadline && $today->gt($deadline);
        $daysLeft = $deadline ? $today->diffInDays($deadline, false) : null;
        $isNear = ! $isLate && $daysLeft !== null && $daysLeft <= 2;

        if ($isLate) {
            $tone = ['bg' => 'bg-red-50', 'border' => 'border-red-200', 'text' => 'text-red-700', 'btn' => 'bg-red-600 hover:bg-red-700', 'icon' => 'error'];
        } elseif ($isNear) {
            $tone = ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-700', 'btn' => 'bg-amber-500 hover:bg-amber-600', 'icon' => 'schedule'];
        } else {
            $tone = ['bg' => 'bg-indigo-50', 'border' => 'border-indigo-200', 'text' => 'text-indigo-700', 'btn' => 'bg-indigo-600 hover:bg-indigo-700', 'icon' => 'assignment'];
        }
    @endphp
    <div class="flex flex-wrap items-center gap-2 rounded-lg border {{ $tone['border'] }} {{ $tone['bg'] }} px-3 py-2">
        <span class="material-symbols-outlined text-[18px] {{ $tone['text'] }}">{{ $tone['icon'] }}</span>
        <div class="min-w-0 flex-1">
            <div class="text-[12px] font-bold {{ $tone['text'] }}">
                @if($isLate)
                    LHP belum dibuat — batas terlewat
                @else
                    LHP belum dibuat
                @endif
            </div>
            @if($deadline)
                <div class="text-[11px] {{ $tone['text'] }} opacity-80">
                    Batas {{ $deadline->translatedFormat('d M Y') }}
                    @if($isLate)
                        · lewat {{ abs($daysLeft) }} hari
                    @elseif($daysLeft === 0)
                        · hari ini
                    @else
                        · sisa {{ $daysLeft }} hari
                    @endif
                </div>
            @endif
        </div>
        <a href="{{ route('employee.travel-reports.create', ['budget_request_id' => $budgetRequest->id]) }}"
           class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-[11px] font-bold text-white {{ $tone['btn'] }} transition-colors">
            <span class="material-symbols-outlined text-[14px]">add</span> Buat LHP
        </a>
    </div>
@endif

{{-- Riwayat penyesuaian item anggaran oleh approver. Butuh relasi revisions.editor. --}}
@php $compact = $compact ?? false; @endphp
@if($revisions->isNotEmpty())
    <div class="space-y-2">
        @foreach($compact ? $revisions->take(1) : $revisions as $revision)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[12.5px] text-amber-900">
                    <span class="material-symbols-outlined text-[16px] text-amber-600">edit_note</span>
                    <span>Disesuaikan oleh <span class="font-bold">{{ $revision->editor?->full_name ?? 'Approver' }}</span></span>
                    @if($revision->step_order)
                        <span class="text-amber-700">· step {{ $revision->step_order }}</span>
                    @endif
                    <span class="text-amber-700">· {{ $revision->created_at?->format('d/m/Y H:i') }}</span>
                </div>
                <div class="mt-1 text-[12.5px] text-amber-900">
                    Total Rp {{ number_format($revision->totalBefore(), 0, ',', '.') }}
                    → <span class="font-black">Rp {{ number_format($revision->totalAfter(), 0, ',', '.') }}</span>
                </div>
                @unless($compact)
                    <ul class="mt-1.5 space-y-0.5 text-[12px] text-amber-800">
                        @foreach($revision->changes() as $change)
                            <li>
                                <span class="font-bold">{{ ['added' => 'Ditambah', 'changed' => 'Diubah', 'removed' => 'Dihapus'][$change['kind']] }}:</span>
                                {{ $change['text'] }}
                            </li>
                        @endforeach
                    </ul>
                @endunless
                @if($revision->notes)
                    <div class="mt-1 text-[12px] text-amber-800"><span class="font-bold">Catatan:</span> {{ $revision->notes }}</div>
                @endif
                @if($compact && $revisions->count() > 1)
                    <div class="mt-1 text-[11px] text-amber-700">+{{ $revisions->count() - 1 }} penyesuaian sebelumnya</div>
                @endif
            </div>
        @endforeach
    </div>
@endif

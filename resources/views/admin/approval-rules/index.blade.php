@extends('admin.layouts.app')
@section('title', 'Pengaturan Approval')

@section('content')
{{-- Tabs for request types --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">settings</span> Pengaturan Aturan Approval</h3>
    </div>
    <div class="p-5">
        <div class="flex gap-0 border-b-2 border-gray-200 mb-5">
            @foreach($types as $typeKey => $typeLabel)
                <a href="{{ route('admin.approval-rules.index', ['type' => $typeKey]) }}"
                   class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200 flex items-center gap-2
                          {{ $activeType === $typeKey ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                    {{ $typeLabel }}
                </a>
            @endforeach
        </div>

        <p class="text-[13px] text-gray-500 mb-4">
            Atur step approval untuk jenis <strong>{{ $types[$activeType] ?? $activeType }}</strong>.
            Rules dikelompokkan berdasarkan <strong>level pemohon</strong> — level yang berbeda bisa punya chain yang berbeda.
        </p>

        {{-- Group rules by requester level --}}
        @php
            $grouped = $rules->groupBy(function($r) {
                if ($r->requester_min_level && $r->requester_max_level) {
                    return $r->requester_min_level == $r->requester_max_level
                        ? "Level {$r->requester_min_level}"
                        : "Level {$r->requester_min_level}-{$r->requester_max_level}";
                }
                return 'Semua Level';
            });
        @endphp

        @if($grouped->isNotEmpty())
        @foreach($grouped as $levelGroup => $levelRules)
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-[12px] font-bold bg-indigo-100 text-indigo-700"><span class="material-symbols-outlined text-[14px] align-text-bottom">person</span> Pemohon {{ $levelGroup }}</span>
                <span class="text-[12px] text-gray-400">{{ $levelRules->count() }} step</span>
            </div>

            <div class="space-y-2 ml-2">
                @foreach($levelRules->sortBy('step_order') as $rule)
                <div class="flex items-center gap-4 p-3.5 rounded-xl border {{ $rule->is_active ? 'border-gray-200 bg-white' : 'border-gray-200 bg-gray-50 opacity-60' }} hover:shadow-sm transition-all duration-200">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-400 text-white flex items-center justify-center text-[13px] font-bold shrink-0">
                        {{ $rule->step_order }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-[13.5px] font-semibold text-gray-900">{{ $rule->name }}</span>
                            @if(!$rule->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-200 text-gray-500">Nonaktif</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 text-[11.5px] text-gray-500">
                            <span>Approver level ≤ <strong>{{ $rule->min_approver_level ?? 'Semua' }}</strong></span>
                            <span>•</span>
                            <span>Role: <strong>{{ ucfirst($rule->approver_role) }}</strong></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <form action="{{ route('admin.approval-rules.toggle', $rule->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-2.5 py-1.5 text-[11px] font-semibold rounded-lg border transition-all duration-200 cursor-pointer
                                    {{ $rule->is_active ? 'text-amber-700 bg-amber-50 border-amber-200 hover:bg-amber-100' : 'text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100' }}">
                                {{ $rule->is_active ? '⏸ Off' : '▶ On' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.approval-rules.destroy', $rule->id) }}" method="POST" onsubmit="return confirm('Hapus step ini?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-2.5 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all duration-200 cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span></button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Flow visualization --}}
            <div class="flex items-center gap-2 mt-2 ml-2 py-2 px-3 bg-gray-50 rounded-lg">
                <span class="text-[11px] font-semibold text-gray-500">Flow:</span>
                <span class="text-[11px] text-gray-600">Submit ({{ $levelGroup }})</span>
                @foreach($levelRules->where('is_active', true)->sortBy('step_order') as $rule)
                    <span class="text-gray-400">→</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-600">{{ $rule->name }}</span>
                @endforeach
                <span class="text-gray-400">→</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-600"><span class="material-symbols-outlined text-[12px] align-text-bottom">check_circle</span> Approved</span>
            </div>
        </div>
        @endforeach
        @else
        <div class="text-center py-10 text-gray-400">
            <div class="text-4xl mb-3"><span class="material-symbols-outlined text-[36px]">list_alt</span></div>
            <p class="text-sm font-medium mb-1">Belum ada aturan approval</p>
            <p class="text-xs">Semua pengajuan akan langsung bisa di-approve oleh admin/manager manapun</p>
        </div>
        @endif

        {{-- Add new step --}}
        <div class="p-4 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 mt-5">
            <h4 class="text-[13px] font-bold text-gray-700 mb-3"><span class="material-symbols-outlined text-[14px] align-text-bottom">add</span> Tambah Step Baru</h4>
            <form action="{{ route('admin.approval-rules.store') }}" method="POST" class="flex items-end gap-3 flex-wrap">
                @csrf
                <input type="hidden" name="request_type" value="{{ $activeType }}">

                <div class="w-[180px]">
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nama Step</label>
                    <input type="text" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" placeholder="cth: Approval Leader" required>
                </div>

                <div class="w-[100px]">
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Level Pemohon Min</label>
                    <input type="number" name="requester_min_level" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" placeholder="4" min="1" max="10">
                </div>

                <div class="w-[100px]">
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Level Pemohon Max</label>
                    <input type="number" name="requester_max_level" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" placeholder="4" min="1" max="10">
                </div>

                <div class="w-[120px]">
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Max Level Approver</label>
                    <input type="number" name="min_approver_level" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" placeholder="cth: 2" min="1">
                </div>

                <div class="w-[120px]">
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Role Approver</label>
                    <select name="approver_role" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_8px_center] bg-no-repeat bg-[length:14px] pr-8 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" required>
                        <option value="any">Semua</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">＋ Tambah</button>
            </form>
        </div>
    </div>
</div>
@endsection

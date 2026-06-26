@extends('employee.layouts.app')
@section('title', 'Ajukan Perubahan Data')

@php
    // Buka tab Ajukan saat ada error validasi; selain itu default ke Ajukan juga.
    $activeTab = 'ajukan';
@endphp

@section('content')
<div class="max-w-2xl mx-auto space-y-5" data-profile-tabs>
    <section class="rounded-xl bg-white border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 sm:p-6">
            <a href="{{ route('employee.profile.show') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Profil Saya
            </a>
            <h1 class="text-[22px] font-black text-gray-900">Ajukan Perubahan Data</h1>
            <p class="text-[13px] text-gray-500 mt-1">Pilih data yang ingin diubah dan ajukan ke admin untuk disetujui.</p>
        </div>

        {{-- Tab Menu --}}
        <div class="border-t border-gray-100 px-3 sm:px-5">
            <div class="flex items-center gap-1 overflow-x-auto -mb-px">
                <button type="button" data-tab-btn="ajukan"
                        class="inline-flex items-center gap-1.5 px-4 py-3 text-[13px] font-bold whitespace-nowrap border-b-2 transition-colors {{ $activeTab === 'ajukan' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                    <span class="material-symbols-outlined text-[18px]">edit_note</span>
                    Ajukan
                </button>
                <button type="button" data-tab-btn="riwayat"
                        class="inline-flex items-center gap-1.5 px-4 py-3 text-[13px] font-bold whitespace-nowrap border-b-2 transition-colors {{ $activeTab === 'riwayat' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                    <span class="material-symbols-outlined text-[18px]">history</span>
                    Riwayat
                    @if($requests->isNotEmpty())
                        <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600">{{ $requests->count() }}</span>
                    @endif
                </button>
            </div>
        </div>
    </section>

    {{-- Panel: Ajukan --}}
    <form data-tab-panel="ajukan" class="{{ $activeTab === 'ajukan' ? '' : 'hidden' }}" action="{{ route('employee.profile.data-change.store') }}" method="POST" enctype="multipart/form-data">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Data yang Diubah</label>
                <select name="field_name" required class="employee-native-field w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
                    <option value="">— Pilih data —</option>
                    @foreach($fields as $key => $label)
                        <option value="{{ $key }}" {{ old('field_name') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('field_name')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Nilai Baru</label>
                <input type="text" name="new_value" value="{{ old('new_value') }}" required maxlength="255"
                       class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500"
                       placeholder="Tuliskan nilai yang benar">
                @error('new_value')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Lampiran</label>
                <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf"
                       class="block w-full text-[12px] text-gray-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:font-bold hover:file:bg-indigo-100">
                <p class="text-[11px] text-gray-400 mt-1">Opsional. Bukti pendukung (mis. KTP). Format jpg, png, atau pdf.</p>
                @error('attachments.*')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-600 transition-all">
                <span class="material-symbols-outlined text-[18px]">send</span>
                Kirim Pengajuan
            </button>
        </div>
    </form>

    {{-- Panel: Riwayat --}}
    <section data-tab-panel="riwayat" class="{{ $activeTab === 'riwayat' ? '' : 'hidden' }} bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 text-[15px] font-bold text-gray-900">Riwayat Pengajuan</div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Data</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Nilai Baru</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100 whitespace-nowrap">{{ $req->created_at?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-gray-800 border-b border-gray-100">{{ $fields[$req->field_name] ?? $req->field_name }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-gray-600 border-b border-gray-100 min-w-[160px] break-words">{{ $req->new_value }}</td>
                            <td class="px-4 py-3.5 border-b border-gray-100 whitespace-nowrap">@include('employee.partials.status-badge', ['status' => $req->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-10 text-[13px] text-gray-400">Belum ada pengajuan perubahan data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const root = document.querySelector('[data-profile-tabs]');
        if (!root) return;

        const buttons = root.querySelectorAll('[data-tab-btn]');
        const panels = root.querySelectorAll('[data-tab-panel]');

        function activate(key) {
            buttons.forEach((btn) => {
                const isActive = btn.dataset.tabBtn === key;
                btn.classList.toggle('border-indigo-600', isActive);
                btn.classList.toggle('text-indigo-700', isActive);
                btn.classList.toggle('border-transparent', !isActive);
                btn.classList.toggle('text-gray-500', !isActive);
                btn.classList.toggle('hover:text-gray-800', !isActive);
            });
            panels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.tabPanel !== key);
            });
        }

        buttons.forEach((btn) => {
            btn.addEventListener('click', () => activate(btn.dataset.tabBtn));
        });
    })();
</script>
@endpush

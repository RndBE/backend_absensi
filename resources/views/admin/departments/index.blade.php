@extends('admin.layouts.app')
@section('title', 'Departemen & Divisi')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">apartment</span> Departemen & Divisi</h3>
        <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">add</span> Tambah
        </button>
    </div>
    <div class="p-5">

        @if($departments->isEmpty())
        <div class="text-center py-10 text-gray-400">
            <div class="text-4xl mb-3"><span class="material-symbols-outlined text-[36px]">apartment</span></div>
            <p class="text-sm font-medium mb-1">Belum ada departemen</p>
            <p class="text-xs text-gray-400">Klik tombol "Tambah" untuk membuat departemen baru</p>
        </div>
        @else
        <div class="space-y-3">
            @foreach($departments as $dept)
            {{-- Parent Division --}}
            <div class="rounded-xl border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-indigo-50 to-white">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-400 text-white flex items-center justify-center text-[13px] font-bold">{{ substr($dept->name, 0, 2) }}</div>
                        <div>
                            <span class="text-[14px] font-bold text-gray-900">{{ $dept->name }}</span>
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-100 text-indigo-600">{{ $dept->employees_count }} org</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="openEditModal({{ $dept->id }}, '{{ addslashes($dept->name) }}', '')" class="px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">edit</span> Edit
                        </button>
                        <form action="{{ route('admin.departments.destroy', $dept->id) }}" method="POST" onsubmit="return confirm('Hapus divisi {{ $dept->name }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-2 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span></button>
                        </form>
                    </div>
                </div>

                @if($dept->children->isNotEmpty())
                <div class="border-t border-gray-100">
                    @foreach($dept->children as $sub)
                    <div class="flex items-center justify-between px-4 py-2.5 pl-12 border-b border-gray-50 last:border-b-0 hover:bg-gray-50 transition-all">
                        <div class="flex items-center gap-2">
                            <span class="text-gray-300">└</span>
                            <span class="text-[13px] font-medium text-gray-700">{{ $sub->name }}</span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-gray-100 text-gray-500">{{ $sub->employees_count }} org</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="openEditModal({{ $sub->id }}, '{{ addslashes($sub->name) }}', '{{ $dept->id }}')" class="px-2.5 py-1.5 text-[10px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer inline-flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">edit</span> Edit
                            </button>
                            <form action="{{ route('admin.departments.destroy', $sub->id) }}" method="POST" onsubmit="return confirm('Hapus sub-divisi {{ $sub->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-2 py-1.5 text-[10px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span></button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

    </div>
</div>

{{-- ═══════════════════════════════════════════════════ --}}
{{-- MODAL: TAMBAH / EDIT DEPARTEMEN                     --}}
{{-- ═══════════════════════════════════════════════════ --}}
<div id="deptModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 hidden">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal()"></div>

    {{-- Modal Content --}}
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-300 scale-95 opacity-0" id="deptModalContent">
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 id="modalTitle" class="text-[15px] font-bold text-gray-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-indigo-500">apartment</span>
                Tambah Departemen
            </h3>
            <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-all cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        {{-- Body --}}
        <form id="deptForm" method="POST" action="{{ route('admin.departments.store') }}">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">

            <div class="px-6 py-5 space-y-4">
                {{-- Nama Departemen --}}
                <div>
                    <label for="deptName" class="block text-[12px] font-semibold text-gray-600 mb-1.5">Nama Departemen</label>
                    <input type="text" name="name" id="deptName"
                           class="w-full px-3.5 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 transition-all"
                           placeholder="cth: HARDWARE" required>
                </div>

                {{-- Parent Department --}}
                <div>
                    <label for="deptParent" class="block text-[12px] font-semibold text-gray-600 mb-1.5">Parent (kosong = divisi utama)</label>
                    <select name="parent_id" id="deptParent"
                            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:14px] pr-9 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 transition-all">
                        <option value="">— Divisi Utama —</option>
                        @foreach($allDepartments as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-2xl">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 text-[13px] font-semibold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-all cursor-pointer">
                    Batal
                </button>
                <button type="submit" id="modalSubmitBtn" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-xl shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">save</span>
                    <span id="modalSubmitText">Tambah</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const deptModal = document.getElementById('deptModal');
    const deptModalContent = document.getElementById('deptModalContent');
    const deptForm = document.getElementById('deptForm');
    const formMethod = document.getElementById('formMethod');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubmitText = document.getElementById('modalSubmitText');
    const deptName = document.getElementById('deptName');
    const deptParent = document.getElementById('deptParent');

    const storeUrl = "{{ route('admin.departments.store') }}";
    const updateUrlBase = "{{ url('admin/departments') }}";

    function openAddModal() {
        // Reset form
        deptForm.action = storeUrl;
        formMethod.value = 'POST';
        deptName.value = '';
        deptParent.value = '';

        modalTitle.innerHTML = '<span class="material-symbols-outlined text-[20px] text-indigo-500">add</span> Tambah Departemen';
        modalSubmitText.textContent = 'Tambah';

        showModal();
    }

    function openEditModal(id, name, parentId) {
        // Set form for edit
        deptForm.action = updateUrlBase + '/' + id;
        formMethod.value = 'PUT';
        deptName.value = name;
        deptParent.value = parentId || '';

        modalTitle.innerHTML = '<span class="material-symbols-outlined text-[20px] text-indigo-500">edit</span> Edit Departemen';
        modalSubmitText.textContent = 'Simpan';

        showModal();
    }

    function showModal() {
        deptModal.classList.remove('hidden');
        // Trigger animation
        requestAnimationFrame(() => {
            deptModalContent.classList.remove('scale-95', 'opacity-0');
            deptModalContent.classList.add('scale-100', 'opacity-100');
        });
        // Focus name input
        setTimeout(() => deptName.focus(), 150);
    }

    function closeModal() {
        deptModalContent.classList.remove('scale-100', 'opacity-100');
        deptModalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            deptModal.classList.add('hidden');
        }, 200);
    }

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !deptModal.classList.contains('hidden')) {
            closeModal();
        }
    });
</script>
@endpush

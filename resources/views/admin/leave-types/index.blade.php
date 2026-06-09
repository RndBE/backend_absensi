@extends('admin.layouts.app')
@section('title', 'Tipe Cuti')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900">
            <span class="material-symbols-outlined text-[18px] align-text-bottom">category</span> Tipe Cuti
        </h3>
        <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">add</span> Tambah Tipe Cuti
        </button>
    </div>

    <div class="overflow-x-auto">
        @if($leaveTypes->isEmpty())
        <div class="text-center py-14 text-gray-400">
            <span class="material-symbols-outlined text-[40px] block mb-2">category</span>
            <p class="text-sm font-medium mb-1">Belum ada tipe cuti</p>
            <p class="text-xs">Klik tombol "Tambah Tipe Cuti" untuk membuat tipe cuti baru.</p>
        </div>
        @else
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Nama Tipe Cuti</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Pengajuan</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($leaveTypes as $lt)
                <tr class="border-b border-gray-50 hover:bg-gray-50/40 transition-all">
                    <td class="py-3.5 px-5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-400 text-white flex items-center justify-center text-[12px] font-bold shrink-0">
                                {{ strtoupper(substr($lt->name, 0, 2)) }}
                            </div>
                            <span class="text-[13px] font-semibold text-gray-800">{{ $lt->name }}</span>
                        </div>
                    </td>
                    <td class="py-3.5 px-5 text-center text-[13px] text-gray-500">
                        {{ $lt->leave_requests_count }} pengajuan
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="openEditModal({{ $lt->id }}, '{{ addslashes($lt->name) }}')"
                                class="px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer inline-flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">edit</span> Edit
                            </button>
                            <form action="{{ route('admin.leave-types.destroy', $lt->id) }}" method="POST"
                                data-confirm="Hapus tipe cuti &quot;{{ $lt->name }}&quot;?">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-2 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all cursor-pointer">
                                    <span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════════════════════════ --}}
{{-- MODAL: TAMBAH / EDIT TIPE CUTI                      --}}
{{-- ═══════════════════════════════════════════════════ --}}
<div id="ltModal" class="fixed inset-0 z-[100] items-center justify-center p-4 hidden" style="display:none">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal()"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-300 scale-95 opacity-0" id="ltModalContent">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 id="modalTitle" class="text-[15px] font-bold text-gray-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-indigo-500">add</span>
                Tambah Tipe Cuti
            </h3>
            <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-all cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <form id="ltForm" method="POST" action="{{ route('admin.leave-types.store') }}">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">

            <div class="px-6 py-5">
                <label for="ltName" class="block text-[12px] font-semibold text-gray-600 mb-1.5">Nama Tipe Cuti</label>
                <input type="text" name="name" id="ltName"
                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 transition-all"
                    placeholder="cth: Cuti Tahunan" required>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-2xl">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 text-[13px] font-semibold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-all cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-xl shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
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
    const ltModal        = document.getElementById('ltModal');
    const ltModalContent = document.getElementById('ltModalContent');
    const ltForm         = document.getElementById('ltForm');
    const formMethod     = document.getElementById('formMethod');
    const modalTitle     = document.getElementById('modalTitle');
    const modalSubmitText = document.getElementById('modalSubmitText');
    const ltName         = document.getElementById('ltName');

    const storeUrl      = "{{ route('admin.leave-types.store') }}";
    const updateUrlBase = "{{ url('admin/leave-types') }}";

    function openAddModal() {
        ltForm.action    = storeUrl;
        formMethod.value = 'POST';
        ltName.value     = '';
        modalTitle.innerHTML = '<span class="material-symbols-outlined text-[20px] text-indigo-500">add</span> Tambah Tipe Cuti';
        modalSubmitText.textContent = 'Tambah';
        showModal();
    }

    function openEditModal(id, name) {
        ltForm.action    = updateUrlBase + '/' + id;
        formMethod.value = 'PUT';
        ltName.value     = name;
        modalTitle.innerHTML = '<span class="material-symbols-outlined text-[20px] text-indigo-500">edit</span> Edit Tipe Cuti';
        modalSubmitText.textContent = 'Simpan';
        showModal();
    }

    function showModal() {
        ltModal.style.display = 'flex';
        requestAnimationFrame(() => {
            ltModalContent.classList.remove('scale-95', 'opacity-0');
            ltModalContent.classList.add('scale-100', 'opacity-100');
        });
        setTimeout(() => ltName.focus(), 150);
    }

    function closeModal() {
        ltModalContent.classList.remove('scale-100', 'opacity-100');
        ltModalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { ltModal.style.display = 'none'; }, 200);
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && ltModal.style.display !== 'none') closeModal();
    });
</script>
@endpush

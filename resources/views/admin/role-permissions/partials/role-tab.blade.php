@php
    $modalInput = 'w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400';
    $modalLabel = 'text-[12px] font-bold text-gray-500 uppercase';
@endphp

<div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-[15px] font-bold text-gray-900">Daftar Role</h2>
            <p class="text-[12px] text-gray-500 mt-0.5">Role baru mengatur hak akses menu admin. Setelah dibuat, atur permission-nya di tab Permission dan pasang ke karyawan di menu <a href="{{ route('admin.roles.index') }}" class="text-indigo-600 hover:underline">Role</a>.</p>
        </div>
        <button type="button" onclick="openRoleFormModal()"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer shrink-0">
            <span class="material-symbols-outlined text-[16px]">add</span> Tambah Role
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                    <th class="py-3 px-4">Role</th>
                    <th class="py-3 px-4">Deskripsi</th>
                    <th class="py-3 px-4">Jenis</th>
                    <th class="py-3 px-4 text-center">Karyawan</th>
                    <th class="py-3 px-4 text-center">Permission Aktif</th>
                    <th class="py-3 px-4 text-center w-40">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($roleRecords as $roleRecord)
                @php
                    $isSuperadmin = $roleRecord->slug === 'superadmin';
                    $activeCount = collect($roleStates[$roleRecord->slug] ?? [])->filter()->count();
                    $payload = $roleRecord->only(['id', 'name', 'slug', 'description']);
                @endphp
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 align-top">
                    <td class="py-3 px-4">
                        <div class="text-[13px] font-semibold text-gray-900">{{ $roleRecord->name }}</div>
                        <div class="text-[11px] text-gray-400 font-mono">{{ $roleRecord->slug }}</div>
                    </td>
                    <td class="py-3 px-4 text-[12.5px] text-gray-600 max-w-[320px]">
                        {{ $roleRecord->description ?: ($isSuperadmin ? 'Selalu punya semua akses.' : '-') }}
                    </td>
                    <td class="py-3 px-4">
                        @if($roleRecord->is_system)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-gray-50 text-gray-600 border-gray-200">Bawaan sistem</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-indigo-50 text-indigo-700 border-indigo-200">Buatan admin</span>
                        @endif
                    </td>
                    <td class="py-3 px-4 text-center text-[13px] text-gray-700">{{ $roleRecord->employees_count }}</td>
                    <td class="py-3 px-4 text-center text-[12px] text-gray-600">
                        {{ $isSuperadmin ? 'Semua' : $activeCount.'/'.$totalPermissionCount }}
                    </td>
                    <td class="py-3 px-4 text-center whitespace-nowrap">
                        @if($isSuperadmin)
                            <span class="text-[11px] text-gray-400">Terkunci</span>
                        @else
                            <button type="button" data-payload="{{ json_encode($payload) }}"
                                    onclick="openRoleFormModal(JSON.parse(this.dataset.payload))"
                                    class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition cursor-pointer">Edit</button>
                            @if($roleRecord->is_system)
                                <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold text-gray-300 bg-gray-50 rounded-lg cursor-not-allowed"
                                      title="Role bawaan sistem dipakai logika aplikasi dan tidak bisa dihapus">Hapus</span>
                            @elseif($roleRecord->employees_count > 0)
                                <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold text-gray-300 bg-gray-50 rounded-lg cursor-not-allowed"
                                      title="Masih dipakai {{ $roleRecord->employees_count }} karyawan">Hapus</span>
                            @else
                                <form method="POST" action="{{ route('admin.roles.destroy', $roleRecord) }}" class="inline"
                                      data-confirm="Hapus role {{ $roleRecord->name }}? Permission yang sudah diatur untuk role ini ikut terhapus.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition cursor-pointer">Hapus</button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">Tabel role belum tersedia. Jalankan migrasi terlebih dahulu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-[11.5px] text-gray-500">
        Role bawaan sistem (Superadmin, HR Admin, Manager, dan lainnya) tidak bisa dihapus karena dipakai logika aplikasi; nama dan deskripsinya tetap bisa diubah.
        Role buatan admin hanya bisa dihapus bila tidak ada karyawan yang memakainya.
    </div>
</div>

{{-- Modal tambah / edit role --}}
<div id="roleFormModal" class="hidden fixed inset-0 overflow-y-auto bg-slate-900/45 px-4 py-6" style="z-index: 1000;">
    <div class="flex min-h-[calc(100vh-3rem)] items-start justify-center sm:items-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-[15px] font-bold text-gray-900" data-role-form-title>Tambah Role</h3>
                <button type="button" onclick="closeRoleFormModal()" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.roles.store') }}" class="p-5 space-y-4"
                  data-store-action="{{ route('admin.roles.store') }}" data-update-action="{{ url('admin/roles') }}">
                @csrf
                <input type="hidden" name="_method" value="POST" data-role-form-method>
                {{-- Hanya untuk membuka ulang modal edit bila validasi gagal; diabaikan server. --}}
                <input type="hidden" name="role_id" value="" data-role-form-id>
                <input type="hidden" name="role_slug" value="" data-role-form-slug-input>
                <div>
                    <label class="{{ $modalLabel }}">Nama Role *</label>
                    <input type="text" name="name" required maxlength="100" value="{{ old('name') }}" placeholder="GA & HSE" class="{{ $modalInput }}">
                    <p class="text-[11px] text-gray-400 mt-1" data-role-form-slug></p>
                </div>
                <div>
                    <label class="{{ $modalLabel }}">Deskripsi</label>
                    <textarea name="description" rows="2" maxlength="500" placeholder="Mengelola surat tugas, kendaraan, dan HT" class="{{ $modalInput }}">{{ old('description') }}</textarea>
                </div>
                <div data-role-form-copy>
                    <label class="{{ $modalLabel }}">Salin Permission Dari (opsional)</label>
                    {{-- Native: dropdown searchable-select tidak ikut ter-reset saat modal dibuka ulang. --}}
                    <select name="copy_from" data-no-search="1" class="{{ $modalInput }} bg-white">
                        <option value="">— Mulai tanpa permission —</option>
                        @foreach($roles as $slug => $label)
                            <option value="{{ $slug }}" @selected(old('copy_from') === $slug)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">Permission bisa diubah lagi kapan saja di tab Permission.</p>
                </div>
                <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer">Simpan</button>
            </form>
        </div>
    </div>
</div>

<script>
function openRoleFormModal(role = null) {
    const modal = document.getElementById('roleFormModal');
    const form = modal.querySelector('form');
    if (role || !form.dataset.keepOld) form.reset();
    form.action = role ? form.dataset.updateAction + '/' + role.id : form.dataset.storeAction;
    form.querySelector('[data-role-form-method]').value = role ? 'PUT' : 'POST';
    form.querySelector('[data-role-form-id]').value = role ? role.id : '';
    form.querySelector('[data-role-form-slug-input]').value = role ? role.slug : '';
    modal.querySelector('[data-role-form-title]').textContent = role ? 'Edit Role' : 'Tambah Role';
    // Slug tidak berubah setelah dibuat, dan salin permission hanya saat menambah.
    modal.querySelector('[data-role-form-copy]').classList.toggle('hidden', !!role);
    modal.querySelector('[data-role-form-slug]').textContent = role ? 'Kode role: ' + role.slug + ' (tetap)' : '';
    if (role) {
        form.querySelector('[name="name"]').value = role.name || '';
        form.querySelector('[name="description"]').value = role.description || '';
    }
    delete form.dataset.keepOld;
    modal.classList.remove('hidden');
    form.querySelector('[name="name"]').focus();
}

function closeRoleFormModal() {
    document.getElementById('roleFormModal').classList.add('hidden');
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeRoleFormModal();
});

@if(($errors->has('name') || $errors->has('description')) && old('role_id'))
    {{-- Validasi gagal saat mengedit: buka lagi modal edit dengan isian lama. --}}
    openRoleFormModal({
        id: @json((int) old('role_id')),
        slug: @json((string) old('role_slug')),
        name: @json((string) old('name')),
        description: @json((string) old('description')),
    });
@elseif($errors->has('name') || $errors->has('description') || $errors->has('copy_from'))
    {{-- Validasi gagal saat menambah role: buka lagi modalnya dengan isian lama. --}}
    document.getElementById('roleFormModal').querySelector('form').dataset.keepOld = '1';
    openRoleFormModal();
@endif
</script>

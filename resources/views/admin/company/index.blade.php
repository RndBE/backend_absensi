@extends('admin.layouts.app')
@section('title', 'Info Perusahaan')

@section('content')
<div class="max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-cyan-500 flex items-center justify-center">
            <span class="material-symbols-outlined text-white text-[20px]">domain</span>
        </div>
        <div>
            <h2 class="text-lg font-bold text-gray-900">Info Perusahaan</h2>
            <p class="text-xs text-gray-500">Kelola informasi dasar perusahaan</p>
        </div>
    </div>

    <form action="{{ route('admin.company.update') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 shadow-sm">
        @csrf
        @method('PUT')

        <div class="p-6 space-y-5">
            {{-- Logo Preview --}}
            <div class="flex items-center gap-5">
                <div class="w-20 h-20 rounded-xl bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden shrink-0" id="logo-preview-container">
                    @if($company && $company->logo)
                        <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo" class="w-full h-full object-contain" id="logo-preview">
                    @else
                        <span class="material-symbols-outlined text-gray-400 text-[32px]" id="logo-placeholder">domain</span>
                        <img src="" alt="Logo" class="w-full h-full object-contain hidden" id="logo-preview">
                    @endif
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-indigo-600 bg-indigo-50 rounded-lg border border-indigo-200 hover:bg-indigo-100 cursor-pointer transition">
                        <span class="material-symbols-outlined text-[16px]">upload</span>
                        Ganti Logo
                        <input type="file" name="logo" accept="image/*" class="hidden" onchange="previewLogo(this)">
                    </label>
                    <p class="text-[11px] text-gray-400 mt-1.5">Format: JPG, PNG. Maks 2MB</p>
                </div>
            </div>

            {{-- Nama Perusahaan --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nama Perusahaan <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $company->name ?? '') }}" required
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
                    placeholder="Contoh: PT Arta Teknologi Comunindo">
                @error('name')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Alamat --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Alamat</label>
                <textarea name="address" rows="3"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition resize-none"
                    placeholder="Alamat lengkap perusahaan">{{ old('address', $company->address ?? '') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Telepon --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone', $company->phone ?? '') }}"
                        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
                        placeholder="021-xxxx-xxxx">
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email', $company->email ?? '') }}"
                        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
                        placeholder="info@perusahaan.com">
                </div>
            </div>

            {{-- NPWP --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">NPWP</label>
                <input type="text" name="npwp" value="{{ old('npwp', $company->npwp ?? '') }}"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
                    placeholder="xx.xxx.xxx.x-xxx.xxx">
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-50/80 border-t border-gray-100 rounded-b-2xl flex justify-end">
            <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-indigo-500 rounded-lg hover:from-indigo-700 hover:to-indigo-600 shadow-sm transition cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">save</span>
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<script>
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('logo-preview');
            const placeholder = document.getElementById('logo-placeholder');
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            if (placeholder) placeholder.classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endsection

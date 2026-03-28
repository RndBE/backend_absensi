@extends('admin.layouts.app')
@section('title', 'Departemen & Divisi')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">apartment</span> Departemen & Divisi</h3>
    </div>
    <div class="p-5">

        @if($departments->isEmpty())
        <div class="text-center py-10 text-gray-400">
            <div class="text-4xl mb-3"><span class="material-symbols-outlined text-[36px]">apartment</span></div>
            <p class="text-sm font-medium mb-1">Belum ada departemen</p>
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
                        {{-- Inline edit --}}
                        <form action="{{ route('admin.departments.update', $dept->id) }}" method="POST" class="flex items-center gap-1.5">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $dept->name }}" class="w-[140px] px-2.5 py-1.5 border border-gray-300 rounded-lg text-[12px] outline-none focus:border-indigo-500">
                            <input type="hidden" name="parent_id" value="">
                            <button type="submit" class="px-2 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">edit</button>
                        </form>
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
                            <form action="{{ route('admin.departments.update', $sub->id) }}" method="POST" class="flex items-center gap-1.5">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $sub->name }}" class="w-[140px] px-2.5 py-1.5 border border-gray-300 rounded-lg text-[11px] outline-none focus:border-indigo-500">
                                <input type="hidden" name="parent_id" value="{{ $dept->id }}">
                                <button type="submit" class="px-2 py-1.5 text-[10px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">edit</button>
                            </form>
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

        {{-- Add New Department --}}
        <div class="p-4 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 mt-5">
            <h4 class="text-[13px] font-bold text-gray-700 mb-3"><span class="material-symbols-outlined text-[14px] align-text-bottom">add</span> Tambah Departemen / Sub-Divisi</h4>
            <form action="{{ route('admin.departments.store') }}" method="POST" class="flex items-end gap-3 flex-wrap">
                @csrf
                <div class="w-[200px]">
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nama</label>
                    <input type="text" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" placeholder="cth: HARDWARE" required>
                </div>

                <div class="w-[200px]">
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Parent (kosong = divisi utama)</label>
                    <select name="parent_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_8px_center] bg-no-repeat bg-[length:14px] pr-8 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">— Divisi Utama —</option>
                        @foreach($allDepartments as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">＋ Tambah</button>
            </form>
        </div>
    </div>
</div>
@endsection

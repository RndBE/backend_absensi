@extends('admin.layouts.app')
@section('title', 'Laporan')

@section('content')
<div class="mb-6">
    <h2 class="text-[20px] font-bold text-gray-900">Pusat Laporan</h2>
    <p class="text-[13px] text-gray-500">Akses semua laporan dan export data</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
    <a href="{{ route('admin.reports.attendance') }}" class="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 overflow-hidden">
        <div class="p-5">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-white text-[24px]">fingerprint</span>
            </div>
            <h3 class="text-[15px] font-bold text-gray-900 mb-1">Laporan Absensi</h3>
            <p class="text-[12px] text-gray-500">Rekap kehadiran, keterlambatan, dan absensi per karyawan/departemen</p>
        </div>
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <span class="text-[11px] font-semibold text-blue-600">Lihat Laporan</span>
            <span class="material-symbols-outlined text-[14px] text-gray-400 group-hover:translate-x-1 transition-transform">arrow_forward</span>
        </div>
    </a>

    <a href="{{ route('admin.reports.leave') }}" class="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 overflow-hidden">
        <div class="p-5">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-white text-[24px]">event_busy</span>
            </div>
            <h3 class="text-[15px] font-bold text-gray-900 mb-1">Laporan Cuti</h3>
            <p class="text-[12px] text-gray-500">Rekap pengambilan cuti, sisa cuti, dan riwayat per karyawan</p>
        </div>
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <span class="text-[11px] font-semibold text-emerald-600">Lihat Laporan</span>
            <span class="material-symbols-outlined text-[14px] text-gray-400 group-hover:translate-x-1 transition-transform">arrow_forward</span>
        </div>
    </a>

    <a href="{{ route('admin.reports.overtime') }}" class="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 overflow-hidden">
        <div class="p-5">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-white text-[24px]">schedule</span>
            </div>
            <h3 class="text-[15px] font-bold text-gray-900 mb-1">Laporan Lembur</h3>
            <p class="text-[12px] text-gray-500">Rekap jam lembur, status approval, dan total per karyawan</p>
        </div>
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <span class="text-[11px] font-semibold text-amber-600">Lihat Laporan</span>
            <span class="material-symbols-outlined text-[14px] text-gray-400 group-hover:translate-x-1 transition-transform">arrow_forward</span>
        </div>
    </a>

    <a href="{{ route('admin.reports.payroll') }}" class="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 overflow-hidden">
        <div class="p-5">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500 to-violet-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-white text-[24px]">payments</span>
            </div>
            <h3 class="text-[15px] font-bold text-gray-900 mb-1">Laporan Payroll</h3>
            <p class="text-[12px] text-gray-500">Rekap gaji, potongan, pajak, BPJS, dan gaji bersih per periode</p>
        </div>
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <span class="text-[11px] font-semibold text-violet-600">Lihat Laporan</span>
            <span class="material-symbols-outlined text-[14px] text-gray-400 group-hover:translate-x-1 transition-transform">arrow_forward</span>
        </div>
    </a>
</div>
@endsection

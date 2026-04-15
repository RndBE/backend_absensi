@extends('admin.layouts.app')
@section('title', 'Detail Karyawan — ' . $employee->full_name)

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <a href="{{ route('admin.employees.index') }}"
            class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke Daftar
        </a>
        <div class="flex gap-2">
            @if($employee->is_active)
            <a href="{{ route('admin.employees.resign', $employee->id) }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-red-600 to-red-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
                <span class="material-symbols-outlined text-[15px]">person_remove</span> Proses Resign
            </a>
            @endif
            <a href="{{ route('admin.employees.edit', $employee->id) }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
                <span class="material-symbols-outlined text-[15px]">edit</span> Edit Data
            </a>
        </div>
    </div>

    {{-- Profile Card --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-5">
        <div class="h-24 bg-gradient-to-br from-indigo-500 via-indigo-400 to-cyan-400 relative">
            <div
                class="absolute inset-0 bg-[url('data:image/svg+xml,%3csvg%20width=%2260%22%20height=%2260%22%20viewBox=%220%200%2060%2060%22%20xmlns=%22http://www.w3.org/2000/svg%22%3e%3cg%20fill=%22none%22%20fill-rule=%22evenodd%22%3e%3cg%20fill=%22%23fff%22%20fill-opacity=%220.06%22%3e%3cpath%20d=%22M36%2034v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6%2034v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6%204V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3e%3c/g%3e%3c/g%3e%3c/svg%3e')] opacity-50">
            </div>
        </div>
        <div class="px-6 pb-6 -mt-10 relative">
            <div class="flex items-end gap-5 py-3 items-center">
                {{-- Avatar --}}
                <div class="w-20 h-20 rounded-full border-4 border-white shadow-md overflow-hidden bg-white shrink-0">
                    @if($employee->photo)
                        <img src="{{ asset('storage/' . $employee->photo) }}" alt="{{ $employee->full_name }}"
                            class="w-full h-full object-cover">
                    @else
                        <div
                            class="w-full h-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-2xl font-bold">
                            {{ substr($employee->full_name, 0, 1) }}</div>
                    @endif
                </div>
                @if($employee->signature)
                <div class="shrink-0 ml-1">
                    <div class="text-[10px] font-bold text-gray-400 uppercase text-center mb-1">Tanda Tangan</div>
                    <div class="w-24 h-12 rounded-lg border border-gray-200 bg-white overflow-hidden flex items-center justify-center">
                        <img src="{{ asset('storage/' . $employee->signature) }}" alt="Signature" class="max-w-full max-h-full object-contain">
                    </div>
                </div>
                @endif
                {{-- Name & Role --}}
                <div class="pb-1 flex-1 min-w-0">
                    <h2 class="text-[18px] font-bold text-gray-900 truncate">{{ $employee->full_name }}</h2>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        <span class="text-[13px] text-gray-500">{{ $employee->position ?? '-' }}</span>
                        <span class="text-gray-300">·</span>
                        <span class="text-[13px] text-gray-500">{{ $employee->department?->name ?? '-' }}</span>
                        <span class="text-gray-300">·</span>
                        @php
                            $roleBg = match ($employee->role) {
                                'superadmin' => 'bg-red-100 text-red-700',
                                'admin' => 'bg-purple-100 text-purple-700',
                                'manager' => 'bg-blue-100 text-blue-700',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold uppercase tracking-wider {{ $roleBg }}">{{ $employee->role }}</span>
                    </div>
                </div>
                {{-- Status Badge --}}
                <div class="pb-1">
                    @if($employee->is_active)
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11.5px] font-bold bg-emerald-100 text-emerald-700"><span
                                class="w-2 h-2 rounded-full bg-emerald-500"></span> Aktif</span>
                    @else
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11.5px] font-bold bg-red-100 text-red-700"><span
                                class="w-2 h-2 rounded-full bg-red-500"></span> Nonaktif</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Left Column --}}
        <div class="lg:col-span-2 space-y-5">
            {{-- Employment Info --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5"><span
                            class="material-symbols-outlined text-[16px] text-indigo-500">business_center</span> Informasi
                        Kepegawaian</h3>
                </div>
                <div class="p-5 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4">
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Kode</div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->employee_code }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Email</div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->email }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">No. HP</div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->phone ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Departemen
                        </div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->department?->name ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Posisi /
                            Jabatan</div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->position ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Level</div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->job_level ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Status
                            Kepegawaian</div>
                        @php
                            $statusBg = match ($employee->employment_status) {
                                'permanent' => 'bg-emerald-100 text-emerald-800',
                                'contract' => 'bg-blue-100 text-blue-800',
                                'intern' => 'bg-gray-100 text-gray-600',
                                default => 'bg-amber-100 text-amber-800',
                            };
                            $statusLabel = match ($employee->employment_status) {
                                'permanent' => 'Tetap',
                                'contract' => 'Kontrak',
                                'intern' => 'Magang',
                                default => 'Probation',
                            };
                        @endphp
                        <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold {{ $statusBg }}">{{ $statusLabel }}</span>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Jadwal Kerja
                        </div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->workSchedule?->name ?? '-' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Atasan /
                            Manager</div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->manager?->full_name ?? '-' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Tanggal
                            Bergabung</div>
                        <div class="text-[13.5px] font-medium text-gray-800">
                            {{ $employee->join_date?->format('d M Y') ?? '-' }}</div>
                    </div>
                    @if(in_array($employee->employment_status, ['contract', 'intern', 'probation']))
                        <div>
                            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Kontrak Mulai
                            </div>
                            <div class="text-[13.5px] font-medium text-gray-800">
                                {{ $employee->contract_start_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Kontrak
                                Berakhir</div>
                            <div class="text-[13.5px] font-medium text-gray-800">
                                {{ $employee->contract_end_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Personal Info --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5"><span
                            class="material-symbols-outlined text-[16px] text-indigo-500">person</span> Informasi Personal
                    </h3>
                </div>
                <div class="p-5 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4">
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">NIK KTP</div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->nik ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Jenis Kelamin
                        </div>
                        <div class="text-[13.5px] font-medium text-gray-800">
                            {{ $employee->gender === 'male' ? 'Laki-laki' : ($employee->gender === 'female' ? 'Perempuan' : '-') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Tempat, Tanggal
                            Lahir</div>
                        <div class="text-[13.5px] font-medium text-gray-800">
                            {{ $employee->birth_place ?? '-' }}{{ $employee->birth_date ? ', ' . $employee->birth_date->format('d M Y') : '' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Agama</div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->religion ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Status
                            Perkawinan</div>
                        @php
                            $maritalLabel = match ($employee->marital_status) {
                                'single' => 'Belum Menikah',
                                'married' => 'Menikah',
                                'divorced' => 'Cerai',
                                'widowed' => 'Cerai Mati',
                                default => '-',
                            };
                        @endphp
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $maritalLabel }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Gol. Darah
                        </div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->blood_type ?? '-' }}</div>
                    </div>
                    <div class="col-span-2 md:col-span-3">
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Alamat KTP
                        </div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->ktp_address ?? '-' }}</div>
                    </div>
                    <div class="col-span-2 md:col-span-3">
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Alamat Domisili
                        </div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->residential_address ?? '-' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Kode Pos</div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->postal_code ?? '-' }}</div>
                    </div>
                </div>
            </div>

            {{-- Approval Chain --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5"><span
                            class="material-symbols-outlined text-[16px] text-indigo-500">approval</span> Approval Chain
                    </h3>
                </div>
                <div class="p-5 space-y-4">
                    @foreach(['leave' => 'Cuti', 'overtime' => 'Lembur', 'attendance' => 'Presensi', 'budget' => 'Anggaran', 'travel_report' => 'LHP'] as $type => $label)
                        <div>
                            <div class="text-[12px] font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $label }}</div>
                            @php $chain = $approvalChains[$type] ?? collect(); @endphp
                            @if($chain->count())
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-gray-100 text-gray-600">
                                        <span class="material-symbols-outlined text-[12px] mr-1">person</span>
                                        {{ $employee->full_name }}
                                    </span>
                                    @foreach($chain->sortBy('step_order') as $step)
                                        <span class="text-gray-300">→</span>
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-indigo-50 text-indigo-600 border border-indigo-100">
                                            <span
                                                class="w-5 h-5 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[9px] font-bold">{{ $step->step_order }}</span>
                                            {{ $step->approver?->full_name ?? '?' }}
                                        </span>
                                    @endforeach
                                    <span class="text-gray-300">→</span>
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-emerald-50 text-emerald-600 border border-emerald-100">✓
                                        Approved</span>
                                </div>
                            @else
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-amber-50 text-amber-600 border border-amber-200">
                                    <span class="material-symbols-outlined text-[12px]">warning</span> Belum diatur
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Right Column --}}
        <div class="space-y-5">
            {{-- BPJS & NPWP --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5"><span
                            class="material-symbols-outlined text-[16px] text-indigo-500">shield</span> BPJS & NPWP</h3>
                </div>
                <div class="p-5 space-y-3.5">
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">PTKP</div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->ptkp ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">NPWP 15 Digit
                        </div>
                        <div class="text-[13.5px] font-medium text-gray-800 font-mono">{{ $employee->npwp_15 ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">NITKU / NPWP 16
                            Digit</div>
                        <div class="text-[13.5px] font-medium text-gray-800 font-mono">{{ $employee->npwp_16 ?? '-' }}</div>
                    </div>
                    <hr class="border-gray-100">
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">BPJS
                            Ketenagakerjaan</div>
                        <div class="text-[13.5px] font-medium text-gray-800 font-mono">{{ $employee->bpjs_tk ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">BPJS Kesehatan
                        </div>
                        <div class="text-[13.5px] font-medium text-gray-800 font-mono">
                            {{ $employee->bpjs_kesehatan ?? '-' }}</div>
                    </div>
                </div>
            </div>

            {{-- Bank Account --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5"><span
                            class="material-symbols-outlined text-[16px] text-indigo-500">account_balance</span> Rekening
                        Bank</h3>
                </div>
                <div class="p-5 space-y-3.5">
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Nama Bank</div>
                        <div class="text-[13.5px] font-medium text-gray-800">{{ $employee->bank_name ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">No. Rekening
                        </div>
                        <div class="text-[13.5px] font-medium text-gray-800 font-mono">{{ $employee->bank_account ?? '-' }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Stats --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5"><span
                            class="material-symbols-outlined text-[16px] text-indigo-500">timeline</span> Ringkasan</h3>
                </div>
                <div class="p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[12.5px] text-gray-500">Lama Bekerja</span>
                        <span class="text-[13px] font-semibold text-gray-800">
                            @if($employee->join_date)
                                @php
                                    $diff = $employee->join_date->diff(now());
                                    $parts = [];
                                    if ($diff->y > 0)
                                        $parts[] = $diff->y . ' tahun';
                                    if ($diff->m > 0)
                                        $parts[] = $diff->m . ' bulan';
                                    if (empty($parts))
                                        $parts[] = $diff->d . ' hari';
                                @endphp
                                {{ implode(' ', $parts) }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    @if(in_array($employee->employment_status, ['contract', 'intern', 'probation']) && $employee->contract_end_date)
                        <div class="flex items-center justify-between">
                            <span class="text-[12.5px] text-gray-500">Sisa Kontrak</span>
                            @php
                                $remaining = now()->diff($employee->contract_end_date);
                                $isExpired = now()->gt($employee->contract_end_date);
                            @endphp
                            <span class="text-[13px] font-semibold {{ $isExpired ? 'text-red-600' : 'text-emerald-600' }}">
                                @if($isExpired)
                                    Sudah habis
                                @else
                                    @php
                                        $rParts = [];
                                        if ($remaining->y > 0)
                                            $rParts[] = $remaining->y . ' thn';
                                        if ($remaining->m > 0)
                                            $rParts[] = $remaining->m . ' bln';
                                        if ($remaining->d > 0)
                                            $rParts[] = $remaining->d . ' hr';
                                    @endphp
                                    {{ implode(' ', $rParts) }}
                                @endif
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
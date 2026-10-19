@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300';
    $label = 'block text-[12px] font-semibold text-gray-600 mb-1';
    $card = 'bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5';
    $heading = 'text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-4';
    $dateValue = fn ($field) => old($field, $letter->{$field}?->toDateString());
    $vehicleOptions = $vehicles->map(fn ($v) => ['id' => $v->id, 'label' => $v->letterLabel()])->values();
    $deviceOptions = $devices->map(fn ($d) => ['id' => $d->id, 'label' => $d->letterLabel()])->values();
    $fixedBoxes = config('assignment_letters.fixed_initial_boxes', []);
    $approvalRequired = \App\Support\AssignmentLetterApprovalFlow::required();
@endphp

<form method="POST" action="{{ $action }}" id="assignmentLetterForm">
    @csrf
    @if($method !== 'POST') @method($method) @endif

    {{-- Surat perbaikan: formulir ini berisi data surat lama; yang disimpan menjadi draft baru yang menaut ke surat itu. --}}
    @if(! empty($revisesLetter))
        <input type="hidden" name="revises_letter_id" value="{{ $revisesLetter->id }}">
        <div class="{{ $card }} !border-indigo-200 !bg-indigo-50/40">
            <h3 class="{{ $heading }}"><span class="material-symbols-outlined text-[16px] text-indigo-500">edit_note</span> Perbaikan atas surat tugas {{ $revisesLetter->number }}</h3>
            <p class="text-[12.5px] text-gray-600 mb-3">
                Data di bawah disalin dari surat {{ $revisesLetter->number }}. Ubah yang perlu diperbaiki, lalu simpan sebagai draft;
                nomor baru keluar saat diterbitkan dan cetakannya berbunyi "memberikan perbaikan surat tugas {{ $revisesLetter->number }}".
            </p>
            <label class="{{ $label }}">Alasan perbaikan <span class="text-gray-400 font-normal">(opsional)</span></label>
            <input type="text" name="revision_reason" maxlength="500" value="{{ old('revision_reason') }}" class="{{ $input }}"
                   placeholder="Mis. salah tujuan, tambah peserta, jadwal berubah">
            <p class="text-[11px] text-gray-400 mt-1">Catatan internal di halaman detail surat perbaikan, supaya kelak jelas kenapa surat diganti. Tidak tercetak di surat.</p>
        </div>
    @endif

    {{-- 1. Informasi surat --}}
    <div class="{{ $card }}">
        <h3 class="{{ $heading }}"><span class="material-symbols-outlined text-[16px] text-indigo-500">description</span> Informasi Surat</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="{{ $label }}">Tanggal Surat <span class="text-red-500">*</span></label>
                <input type="date" name="letter_date" id="letter_date" value="{{ $dateValue('letter_date') }}" required class="{{ $input }}">
                <p class="text-[11px] text-gray-400 mt-1">Bulan & tahun di nomor surat mengikuti tanggal ini.</p>
            </div>
            <div>
                <label class="{{ $label }}">Kota <span class="text-red-500">*</span></label>
                <input type="text" name="city" value="{{ old('city', $letter->city) }}" required maxlength="100" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Penanda Tangan <span class="text-red-500">*</span></label>
                {{-- Tanpa "required": searchable-select menyembunyikan select asli, dan browser menolak
                     submit diam-diam bila field required yang tersembunyi kosong. Server tetap memvalidasi. --}}
                <select name="signer_id" id="signer_id" class="{{ $input }}">
                    <option value="">— Pilih —</option>
                    @foreach($signers as $signer)
                        <option value="{{ $signer->id }}" data-name="{{ $signer->full_name }}" data-position="{{ $signer->position }}"
                                @selected((string) old('signer_id', $letter->signer_id) === (string) $signer->id)>
                            {{ $signer->full_name }}{{ $signer->position ? ' — '.$signer->position : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="{{ $label }}">Nama Penanda Tangan di Surat <span class="text-red-500">*</span></label>
                <input type="text" name="signer_name" id="signer_name" value="{{ old('signer_name', $letter->signer_name) }}" required maxlength="255" class="{{ $input }}" placeholder="Sofyan Ariyanto, S.T">
                <p class="text-[11px] text-gray-400 mt-1">Boleh ditambah gelar. Surat berikutnya otomatis memakai nama & jabatan ini.</p>
            </div>
            <div>
                <label class="{{ $label }}">Jabatan Penanda Tangan <span class="text-red-500">*</span></label>
                <input type="text" name="signer_position" id="signer_position" value="{{ old('signer_position', $letter->signer_position) }}" required maxlength="255" class="{{ $input }}" placeholder="Direktur">
            </div>
        </div>
    </div>

    {{-- 2. Jadwal (di atas karyawan: tanggalnya dipakai untuk cek bentrok peserta) --}}
    <div class="{{ $card }}">
        <h3 class="{{ $heading }}"><span class="material-symbols-outlined text-[16px] text-indigo-500">date_range</span> Jadwal</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="{{ $label }}">Berangkat <span class="text-red-500">*</span></label>
                <input type="date" name="departure_date" id="departure_date" value="{{ $dateValue('departure_date') }}" required class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Mulai Bekerja <span class="text-red-500">*</span></label>
                <input type="date" name="work_start_date" id="work_start_date" value="{{ $dateValue('work_start_date') }}" required class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Kembali <span class="text-red-500">*</span></label>
                <input type="date" name="return_date" id="return_date" value="{{ $dateValue('return_date') }}" required class="{{ $input }}">
            </div>
        </div>
        <div id="schedulePreview" class="mt-4 px-4 py-3 rounded-lg bg-indigo-50 border border-indigo-100 text-[12.5px] text-indigo-900"></div>
    </div>

    {{-- 3. Karyawan: kiri daftar semua karyawan per divisi, kanan yang terpilih --}}
    <div class="{{ $card }}" id="employeePicker"
         data-availability-url="{{ route('admin.assignment-letters.availability') }}"
         data-exclude="{{ $letter->exists ? $letter->id : '' }}">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <h3 class="{{ $heading }} !mb-0"><span class="material-symbols-outlined text-[16px] text-indigo-500">group</span> Karyawan yang Ditugaskan</h3>
            <span id="availabilityStatus" class="text-[11.5px] text-gray-400"></span>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <div class="p-3 border-b border-gray-100 bg-gray-50">
                    <div class="relative">
                        <span class="material-symbols-outlined text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" style="font-size: 18px">search</span>
                        <input type="text" id="employeeSearch" autocomplete="off" class="{{ $input }} bg-white pl-10 pr-9" placeholder="Cari nama, nomor karyawan, jabatan, atau divisi">
                        <button type="button" id="employeeSearchClear" title="Hapus pencarian"
                                class="hidden absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 inline-flex items-center justify-center rounded-md text-gray-400 hover:bg-gray-200 hover:text-gray-700 cursor-pointer">
                            <span class="material-symbols-outlined" style="font-size: 16px">close</span>
                        </button>
                    </div>
                    <div id="employeeListInfo" class="text-[11px] text-gray-500 mt-2"></div>
                </div>
                <div id="employeeList" class="max-h-96 overflow-y-auto"></div>
            </div>
            <div class="border border-gray-200 rounded-lg overflow-hidden self-start">
                <div class="px-3 py-2.5 border-b border-gray-100 bg-gray-50 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[12px] font-bold text-gray-700">Terpilih (<span id="selectedCount">0</span>)</span>
                    <span class="text-[11px] text-gray-400">Urutan di sini = urutan di surat</span>
                </div>
                <div id="employeeRows" class="divide-y divide-gray-100"></div>
                <p id="employeeEmpty" class="text-[12px] text-gray-400 text-center px-4 py-8">Belum ada karyawan dipilih. Centang nama di daftar.</p>
            </div>
        </div>
        <p class="text-[11px] text-gray-400 mt-3">
            Hanya karyawan aktif berstatus tetap, kontrak, atau probation; magang dan outsourcing tidak muncul. Nama dan nomor karyawan diambil dari data karyawan; jabatan boleh ditulis ulang sesuai surat.
            Tanda <span class="font-semibold text-amber-700">peringatan kuning</span> = cuti atau sudah tercantum di surat tugas lain pada tanggal jadwal (surat tetap boleh disimpan).
        </p>
    </div>

    {{-- 4. Tujuan --}}
    <div class="{{ $card }}">
        <h3 class="{{ $heading }}"><span class="material-symbols-outlined text-[16px] text-indigo-500">flag</span> Tujuan & Kegiatan</h3>
        <div class="mb-4">
            <label class="{{ $label }}">Kalimat Pembuka "Untuk ..." (opsional)</label>
            <div class="flex items-center gap-2">
                <span class="text-[13px] text-gray-500 shrink-0">Untuk</span>
                <input type="text" name="purpose" value="{{ old('purpose', $letter->purpose) }}" maxlength="500" class="{{ $input }}" placeholder="melaksanakan beberapa pekerjaan">
                <span class="text-[13px] text-gray-500 shrink-0">:</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Kosongkan bila tujuannya satu: kalimat tujuan langsung disambung setelah "Untuk".</p>
        </div>
        <div id="destinationRows" class="space-y-3"></div>
        <button type="button" id="addDestination" class="mt-3 text-[12px] font-semibold text-indigo-600 hover:underline cursor-pointer">+ Tambah lokasi</button>
    </div>

    {{-- 5. Perangkat & transportasi --}}
    <div class="{{ $card }}">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
            <h3 class="{{ $heading }} !mb-0"><span class="material-symbols-outlined text-[16px] text-indigo-500">directions_car</span> Perangkat Komunikasi & Transportasi</h3>
            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-teal-50 text-teal-700 border border-teal-200">Diisi GA&HSE</span>
        </div>
        <p class="text-[11px] text-gray-400 mb-4">
            Kendaraan dan HT milik perusahaan dipilih dari daftar @if(app(\App\Support\AdminPermission::class)->can($currentAdmin, 'assignment_facilities.manage'))<a href="{{ route('admin.assignment-facilities.index') }}" target="_blank" class="text-indigo-600 hover:underline">Kendaraan & HT</a>@else Kendaraan & HT @endif.
            Untuk <strong>kendaraan pribadi atau rental</strong>, pilih "Kendaraan pribadi…" atau "Kendaraan rental / sewa…" lalu isi merek dan plat; tercetak seperti "Vario / milik pribadi / AB 1234 XX".
            Kereta api, pesawat, bus, dan shuttle ada di pilihan; selain itu ketik sendiri di kolom Keterangan. Baris yang keterangannya kosong tercetak "-".
        </p>

        <div class="mb-5">
            <div class="text-[12px] font-bold text-gray-700 mb-2">Perangkat Komunikasi</div>
            <div id="deviceRows" class="space-y-3"></div>
            <button type="button" data-add-facility="device" class="mt-2 text-[12px] font-semibold text-indigo-600 hover:underline cursor-pointer">+ Tambah perangkat</button>
        </div>
        <div>
            <div class="text-[12px] font-bold text-gray-700 mb-2">Transportasi</div>
            <div id="transportRows" class="space-y-3"></div>
            <button type="button" data-add-facility="transport" class="mt-2 text-[12px] font-semibold text-indigo-600 hover:underline cursor-pointer">+ Tambah transportasi</button>
        </div>
    </div>

    {{-- 6. Paraf & catatan --}}
    <div class="{{ $card }}">
        <h3 class="{{ $heading }}"><span class="material-symbols-outlined text-[16px] text-indigo-500">draw</span> Kolom Paraf & Catatan</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="{{ $label }}">{{ $approvalRequired ? 'Kolom Paraf Tambahan (pisahkan dengan koma)' : 'Kolom Paraf (pisahkan dengan koma)' }}</label>
                <input type="text" name="initial_boxes" id="initial_boxes" maxlength="500" class="{{ $input }}"
                       value="{{ old('initial_boxes', $letter->initial_boxes ? implode(', ', $letter->initial_boxes) : '') }}">
                @if($approvalRequired)
                    <p class="text-[11px] text-gray-400 mt-1">Kolom otomatis, diisi tanda tangan digital lewat persetujuan: <span id="initialBoxesAuto" class="font-semibold text-gray-600"></span></p>
                    <p class="text-[11px] text-gray-400">Otomatis = atasan level 3 lalu level 2 tiap peserta (rantai approval cuti), kemudian HRD dan GA HSE. Isi kolom ini hanya untuk kolom paraf basah tambahan; biasanya kosong.</p>
                @else
                    <p class="text-[11px] text-gray-400 mt-1">Kosongkan untuk otomatis: <span id="initialBoxesAuto" class="font-semibold text-gray-600"></span></p>
                    <p class="text-[11px] text-gray-400">Otomatis = atasan level 3 lalu level 2 dari tiap peserta (menurut rantai approval cuti), kemudian HRD dan GA HSE.</p>
                @endif
            </div>
            <div>
                <label class="{{ $label }}">Catatan Internal</label>
                <textarea name="notes" rows="2" maxlength="2000" class="{{ $input }}" placeholder="Tidak ikut tercetak di surat">{{ old('notes', $letter->notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ $cancelUrl }}" class="px-5 py-2.5 text-[13px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">Batal</a>
        <button type="submit" class="px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Simpan Draft</button>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const EMPLOYEES = @json($pickerEmployees);
    const VEHICLES = @json($vehicleOptions);
    const DEVICES = @json($deviceOptions);
    const INSPECTION = @json(array_values($inspectionSuggestions));
    const CUSTOM_RESULT = '__custom';
    const INITIAL = @json($initialRows);
    const FIXED_BOXES = @json($fixedBoxes);
    const INPUT = @json($input);
    const byId = new Map(EMPLOYEES.map(e => [String(e.id), e]));
    let seq = 0;

    function el(tag, attrs = {}, children = []) {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(attrs)) {
            if (value === null || value === undefined || value === false) continue;
            if (key === 'text') node.textContent = value;
            else if (key === 'class') node.className = value;
            else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
            else node.setAttribute(key, value === true ? '' : value);
        }
        [].concat(children).forEach(child => child && node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child));
        return node;
    }

    function iconButton(icon, title, onclick, extra = '') {
        return el('button', {
            type: 'button', title, onclick,
            class: 'w-7 h-7 inline-flex items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-700 cursor-pointer ' + extra,
        }, [el('span', {class: 'material-symbols-outlined text-[18px]', text: icon})]);
    }

    // ---------- Karyawan ----------
    const picker = document.getElementById('employeePicker');
    const rowsBox = document.getElementById('employeeRows');
    const listBox = document.getElementById('employeeList');
    const searchInput = document.getElementById('employeeSearch');
    const searchClear = document.getElementById('employeeSearchClear');
    const availabilityStatus = document.getElementById('availabilityStatus');
    let availability = {};          // employee_id => peringatan bentrok pada tanggal jadwal
    let availabilityState = 'idle';  // idle | loading | ok | error

    const conflictsOf = id => availability[String(id)] || [];
    const matchesQuery = (emp, q) => !q || [emp.name, emp.code, emp.department, emp.position].some(v => v && v.toLowerCase().includes(q));

    function selectedIds() {
        return [...rowsBox.querySelectorAll('input[data-employee-id]')].map(i => i.value);
    }

    function conflictLines(id) {
        return conflictsOf(id).map(text => el('div', {class: 'flex items-start gap-1 text-[11px] font-semibold text-amber-700'}, [
            el('span', {class: 'material-symbols-outlined', style: 'font-size: 14px', text: 'warning'}),
            el('span', {text}),
        ]));
    }

    function renderAvailabilityStatus() {
        const selectedBusy = selectedIds().filter(id => conflictsOf(id).length).length;
        const busy = Object.keys(availability).length;
        const text = {
            idle: '',
            loading: 'Mengecek jadwal karyawan…',
            error: 'Jadwal karyawan belum bisa dicek.',
            ok: busy
                ? `${busy} karyawan cuti/bertugas di tanggal ini` + (selectedBusy ? ` · ${selectedBusy} di antaranya terpilih` : '')
                : 'Tidak ada karyawan yang bentrok di tanggal ini',
        }[availabilityState];
        availabilityStatus.textContent = text;
        availabilityStatus.className = 'text-[11.5px] font-semibold ' + (selectedBusy ? 'text-amber-700' : 'text-gray-400');
    }

    function renumberEmployees() {
        const rows = [...rowsBox.children];
        rows.forEach((row, i) => row.querySelector('[data-number]').textContent = (i + 1) + '.');
        document.getElementById('employeeEmpty').classList.toggle('hidden', rows.length > 0);
        rowsBox.classList.toggle('hidden', rows.length === 0);
        document.getElementById('selectedCount').textContent = rows.length;
        updateInitialBoxes();
        renderList();
        renderAvailabilityStatus();
    }

    function renderSelectedConflicts() {
        rowsBox.querySelectorAll('[data-employee-row]').forEach(row => {
            row.querySelector('[data-conflicts]').replaceChildren(...conflictLines(row.dataset.employeeRow));
        });
    }

    // skipRender: dipakai "Pilih semua" supaya daftar dirender sekali di akhir.
    function addEmployee(id, position = null, skipRender = false) {
        const emp = byId.get(String(id));
        if (!emp || selectedIds().includes(String(emp.id))) return;
        const key = seq++;
        const moveRow = (e, up) => {
            const row = e.currentTarget.closest('[data-employee-row]');
            const target = up ? row.previousElementSibling : row.nextElementSibling;
            if (target) up ? row.parentNode.insertBefore(row, target) : row.parentNode.insertBefore(target, row);
            renumberEmployees();
        };
        const row = el('div', {class: 'px-3 py-2.5' + (emp.eligible ? '' : ' bg-red-50'), 'data-employee-row': emp.id}, [
            el('div', {class: 'flex items-start gap-2'}, [
                el('span', {class: 'w-6 pt-0.5 text-[12px] font-bold text-gray-400 shrink-0', 'data-number': true}),
                el('div', {class: 'min-w-0 flex-1'}, [
                    el('div', {class: 'text-[13px] font-semibold text-gray-900', text: emp.name}),
                    el('div', {class: 'text-[11px] text-gray-400', text: [emp.code, emp.department].filter(Boolean).join(' · ') || '-'}),
                    emp.eligible ? null : el('div', {class: 'text-[11px] font-semibold text-red-600', text: 'Tidak memenuhi syarat (magang/outsourcing/nonaktif). Hapus dari surat.'}),
                    el('div', {class: 'mt-0.5 space-y-0.5', 'data-conflicts': true}, conflictLines(emp.id)),
                ]),
                el('div', {class: 'flex items-center shrink-0'}, [
                    iconButton('arrow_upward', 'Naikkan', e => moveRow(e, true)),
                    iconButton('arrow_downward', 'Turunkan', e => moveRow(e, false)),
                    iconButton('close', 'Keluarkan dari surat', () => removeEmployee(emp.id), 'hover:!text-red-600'),
                ]),
            ]),
            el('input', {type: 'hidden', name: `employees[${key}][employee_id]`, value: emp.id, 'data-employee-id': true}),
            el('label', {class: 'block mt-2 pl-8'}, [
                el('span', {class: 'block text-[10.5px] font-semibold uppercase tracking-wider text-gray-400 mb-0.5', text: 'Jabatan di surat'}),
                el('input', {type: 'text', name: `employees[${key}][position]`, value: position ?? emp.position ?? '', maxlength: 255,
                    placeholder: emp.position || 'Jabatan', class: INPUT + ' !py-1.5'}),
            ]),
        ]);
        rowsBox.appendChild(row);
        if (!skipRender) renumberEmployees();
    }

    function removeEmployee(id, skipRender = false) {
        rowsBox.querySelector(`[data-employee-row="${id}"]`)?.remove();
        if (!skipRender) renumberEmployees();
    }

    function renderList() {
        const q = searchInput.value.trim().toLowerCase();
        searchClear.classList.toggle('hidden', !q);
        const taken = new Set(selectedIds());
        const eligible = EMPLOYEES.filter(e => e.eligible);
        const matches = eligible.filter(e => matchesQuery(e, q));

        const groups = new Map();
        matches.forEach(e => {
            const name = e.department || 'Tanpa divisi';
            if (!groups.has(name)) groups.set(name, []);
            groups.get(name).push(e);
        });

        const nodes = [];
        [...groups.keys()].sort((a, b) => a.localeCompare(b)).forEach(name => {
            const members = groups.get(name);
            const allTaken = members.every(e => taken.has(String(e.id)));
            nodes.push(el('div', {class: 'sticky top-0 z-10 flex items-center justify-between gap-2 px-3 py-1.5 bg-gray-100 border-y border-gray-200'}, [
                el('span', {class: 'text-[11px] font-bold uppercase tracking-wider text-gray-600 truncate', text: `${name} · ${members.length}`}),
                el('button', {
                    type: 'button', class: 'shrink-0 text-[11px] font-semibold text-indigo-600 hover:underline cursor-pointer',
                    text: allTaken ? 'Batalkan semua' : 'Pilih semua',
                    onclick: () => {
                        members.forEach(e => allTaken ? removeEmployee(e.id, true) : addEmployee(e.id, null, true));
                        renumberEmployees();
                    },
                }),
            ]));
            members.forEach(e => {
                const checked = taken.has(String(e.id));
                nodes.push(el('label', {class: 'flex items-start gap-2.5 px-3 py-2 cursor-pointer hover:bg-indigo-50' + (checked ? ' bg-indigo-50/70' : '')}, [
                    el('input', {
                        type: 'checkbox', checked, class: 'mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500',
                        onchange: ev => ev.target.checked ? addEmployee(e.id) : removeEmployee(e.id),
                    }),
                    el('div', {class: 'min-w-0 flex-1'}, [
                        el('div', {class: 'text-[13px] font-semibold text-gray-800', text: e.name}),
                        el('div', {class: 'text-[11px] text-gray-400', text: [e.code, e.position].filter(Boolean).join(' · ') || '-'}),
                        ...conflictLines(e.id),
                    ]),
                ]));
            });
        });

        listBox.replaceChildren(...(nodes.length ? nodes : [el('div', {class: 'px-3 py-8 text-center text-[12px] text-gray-400', text: 'Tidak ada karyawan yang cocok.'})]));
        document.getElementById('employeeListInfo').textContent = q
            ? `${matches.length} dari ${eligible.length} karyawan cocok · Enter untuk menambahkan yang teratas`
            : `${eligible.length} karyawan · centang untuk menambahkan`;
    }

    searchInput.addEventListener('input', renderList);
    searchInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const q = searchInput.value.trim().toLowerCase();
            const taken = selectedIds();
            const first = EMPLOYEES.find(emp => emp.eligible && !taken.includes(String(emp.id)) && matchesQuery(emp, q));
            if (first) addEmployee(first.id);
        } else if (e.key === 'Escape') {
            searchInput.value = '';
            renderList();
        }
    });
    searchClear.addEventListener('click', () => { searchInput.value = ''; renderList(); searchInput.focus(); });

    // Cek bentrok (cuti / surat tugas lain) setiap kali tanggal berangkat atau kembali berubah.
    let availabilityTimer = null;
    let availabilityRequest = 0;

    function scheduleAvailability() {
        clearTimeout(availabilityTimer);
        availabilityTimer = setTimeout(loadAvailability, 300);
    }

    async function loadAvailability() {
        const from = document.getElementById('departure_date').value;
        const to = document.getElementById('return_date').value;
        const token = ++availabilityRequest;

        if (!from || !to || to < from) {
            availability = {};
            availabilityState = 'idle';
        } else {
            availabilityState = 'loading';
            renderAvailabilityStatus();
            try {
                const params = new URLSearchParams({from, to});
                if (picker.dataset.exclude) params.set('exclude', picker.dataset.exclude);
                const response = await fetch(`${picker.dataset.availabilityUrl}?${params}`, {headers: {Accept: 'application/json'}});
                if (!response.ok) throw new Error(response.status);
                const body = await response.json();
                if (token !== availabilityRequest) return;
                availability = body.data || {};
                availabilityState = 'ok';
            } catch (error) {
                if (token !== availabilityRequest) return;
                availability = {};
                availabilityState = 'error';
            }
        }

        renderList();
        renderSelectedConflicts();
        renderAvailabilityStatus();
    }

    // ---------- Tujuan ----------
    const destBox = document.getElementById('destinationRows');

    function renumberDestinations() {
        const rows = [...destBox.children];
        rows.forEach((row, i) => {
            row.querySelector('[data-number]').textContent = 'Lokasi ' + (i + 1);
            row.querySelector('[data-remove]').classList.toggle('invisible', rows.length === 1);
        });
    }

    function addDestination(data = {}) {
        const key = seq++;
        const row = el('div', {class: 'border border-gray-200 rounded-lg p-3', 'data-row': true}, [
            el('div', {class: 'flex items-center justify-between mb-2'}, [
                el('span', {class: 'text-[11px] font-bold uppercase tracking-wider text-indigo-600', 'data-number': true}),
                el('button', {type: 'button', 'data-remove': true, class: 'text-[11px] font-semibold text-red-500 hover:text-red-700 cursor-pointer', text: 'Hapus',
                    onclick: e => { e.currentTarget.closest('[data-row]').remove(); renumberDestinations(); }}),
            ]),
            el('div', {class: 'grid grid-cols-1 md:grid-cols-12 gap-3'}, [
                el('div', {class: 'md:col-span-5'}, [
                    el('label', {class: 'block text-[11px] font-semibold text-gray-500 mb-1', text: 'Kegiatan & nama lokasi *'}),
                    el('textarea', {name: `destinations[${key}][activity]`, rows: 3, required: true, maxlength: 1000, class: INPUT,
                        placeholder: 'Riset dan update firmware di Bendungan Ciawi'}, [data.activity || '']),
                ]),
                el('div', {class: 'md:col-span-5'}, [
                    el('label', {class: 'block text-[11px] font-semibold text-gray-500 mb-1', text: 'Alamat'}),
                    el('textarea', {name: `destinations[${key}][address]`, rows: 3, maxlength: 1000, class: INPUT,
                        placeholder: 'Jl. Panatraco, Cipayung Datar, Megamendung, Bogor, Jawa Barat'}, [data.address || '']),
                ]),
                el('div', {class: 'md:col-span-2'}, [
                    el('label', {class: 'block text-[11px] font-semibold text-gray-500 mb-1', text: 'Jarak (km)'}),
                    el('input', {type: 'number', name: `destinations[${key}][distance_km]`, step: '0.1', min: 0, max: 99999, class: INPUT,
                        value: data.distance_km ?? '', placeholder: '590'}),
                    el('p', {class: 'text-[10.5px] text-gray-400 mt-1', text: 'dari kantor'}),
                ]),
            ]),
        ]);
        destBox.appendChild(row);
        renumberDestinations();
    }

    document.getElementById('addDestination').addEventListener('click', () => addDestination());

    // ---------- Perangkat & transportasi ----------
    // Kendaraan bukan milik perusahaan tidak ada di daftar master. Pilihan "pribadi"/"rental" membuka
    // isian merek & plat yang disusun menjadi "Vario / milik pribadi / AB 1234 XX", persis seperti di
    // surat Word. Kolom "Keterangan di surat" tetap satu-satunya sumber teks yang tercetak.
    const PUBLIC_TRANSPORT = ['Kereta Api', 'Pesawat', 'Bus', 'Shuttle'];
    const OWNED_LABEL = {personal: 'milik pribadi', rental: 'milik rental'};
    const OWNED_PATTERN = /^(?:(.*?)\s*\/\s*)?milik\s+(pribadi|rental)\s*(?:\/\s*(.*))?$/i;

    // Pilihan awal baris transportasi dari surat yang disimpan (kendaraan master, pribadi/rental, atau umum).
    function transportChoice(data) {
        if (data.vehicle_id) return {value: String(data.vehicle_id)};
        const text = (data.description || '').trim();
        const owned = OWNED_PATTERN.exec(text);
        if (owned) return {value: owned[2].toLowerCase() === 'pribadi' ? 'personal' : 'rental', name: (owned[1] || '').trim(), plate: (owned[3] || '').trim()};
        const open = PUBLIC_TRANSPORT.find(name => name.toLowerCase() === text.toLowerCase());
        return {value: open ? 'public:' + open : ''};
    }

    function addFacility(type, data = {}) {
        const key = seq++;
        const isTransport = type === 'transport';
        const masters = isTransport ? VEHICLES : DEVICES;
        const masterField = isTransport ? 'vehicle_id' : 'communication_device_id';
        const choice = isTransport ? transportChoice(data) : {value: data[masterField] ? String(data[masterField]) : ''};
        const description = el('input', {type: 'text', name: `facilities[${key}][description]`, maxlength: 500, class: INPUT,
            value: data.description || '', placeholder: isTransport ? 'Ketik sendiri, mis. Bus Damri atau Travel' : 'Kosongkan bila tidak membawa HT'});
        // Hanya id master yang dikirim; pilihan pribadi/rental/umum tidak punya id.
        const masterInput = el('input', {type: 'hidden', name: `facilities[${key}][${masterField}]`, value: masters.some(m => String(m.id) === choice.value) ? choice.value : ''});

        const options = [
            // Pilihan kosong = tidak memakai daftar: teks yang diketik di kolom Keterangan itulah yang tercetak.
            el('option', {value: '', text: isTransport ? '— Lainnya, ketik di Keterangan —' : '— Tidak membawa / ketik di Keterangan —'}),
            ...masters.map(m => el('option', {value: m.id, text: m.label, selected: String(m.id) === choice.value})),
        ];
        if (isTransport) {
            options.push(
                el('option', {value: 'personal', text: 'Kendaraan pribadi…', selected: choice.value === 'personal'}),
                el('option', {value: 'rental', text: 'Kendaraan rental / sewa…', selected: choice.value === 'rental'}),
                ...PUBLIC_TRANSPORT.map(name => el('option', {value: 'public:' + name, text: name, selected: choice.value === 'public:' + name})),
            );
        }
        const select = el('select', {class: INPUT}, options);

        // Isian merek & plat untuk kendaraan pribadi/rental.
        const ownedName = el('input', {type: 'text', maxlength: 150, class: INPUT, value: choice.name || '', placeholder: 'Merek / jenis, mis. Vario'});
        const ownedPlate = el('input', {type: 'text', maxlength: 30, class: INPUT, value: choice.plate || '', placeholder: 'Plat nomor, mis. AB 1234 XX'});
        const ownedRow = el('div', {class: 'grid grid-cols-1 md:grid-cols-12 gap-3 mt-3' + (OWNED_LABEL[choice.value] ? '' : ' hidden')}, [
            el('div', {class: 'md:col-span-3'}, [el('label', {class: 'block text-[11px] font-semibold text-gray-500 mb-1', text: 'Merek / jenis kendaraan'}), ownedName]),
            el('div', {class: 'md:col-span-3'}, [el('label', {class: 'block text-[11px] font-semibold text-gray-500 mb-1', text: 'Plat nomor'}), ownedPlate]),
        ]);

        const owned = () => [ownedName.value.trim(), OWNED_LABEL[select.value], ownedPlate.value.trim()].filter(Boolean).join(' / ');
        // Teks otomatis hanya menimpa keterangan yang kosong atau belum diubah tangan.
        let autoText = description.value;
        const setAuto = text => {
            if (!description.value.trim() || description.value === autoText) description.value = text;
            autoText = text;
        };
        const refreshOwned = () => { if (OWNED_LABEL[select.value]) { description.value = owned(); autoText = description.value; } };
        ownedName.addEventListener('input', refreshOwned);
        ownedPlate.addEventListener('input', refreshOwned);

        select.addEventListener('change', () => {
            const master = masters.find(m => String(m.id) === select.value);
            masterInput.value = master ? String(master.id) : '';
            ownedRow.classList.toggle('hidden', !OWNED_LABEL[select.value]);

            if (master) setAuto(master.label);
            else if (OWNED_LABEL[select.value]) { setAuto(owned()); if (!ownedName.value) ownedName.focus(); }
            else if (select.value.startsWith('public:')) setAuto(select.value.slice(7));
            else setAuto('');
        });

        const inspectedOn = el('input', {type: 'date', name: `facilities[${key}][inspected_on]`, value: data.inspected_on || '', class: INPUT});
        // Hasil pemeriksaan: dropdown bergaya sama dengan dropdown lain. Hasil yang bukan pilihan baku
        // ditulis lewat "Lainnya"; nilai akhirnya ada di input tersembunyi (satu-satunya yang dikirim).
        const savedResult = (data.inspection_result || '').trim();
        const customResult = savedResult !== '' && !INSPECTION.includes(savedResult);
        const resultValue = el('input', {type: 'hidden', name: `facilities[${key}][inspection_result]`, value: savedResult});
        const resultCustom = el('input', {type: 'text', maxlength: 255, class: INPUT, value: customResult ? savedResult : '', placeholder: 'Tulis hasil pemeriksaan'});
        const resultCustomBox = el('div', {class: 'mt-2' + (customResult ? '' : ' hidden')}, [resultCustom]);
        const result = el('select', {class: INPUT}, [
            el('option', {value: '', text: '— Belum diperiksa —'}),
            ...INSPECTION.map(text => el('option', {value: text, text, selected: text === savedResult})),
            el('option', {value: CUSTOM_RESULT, text: 'Lainnya, ketik sendiri…', selected: customResult}),
        ]);
        const syncResult = () => {
            const custom = result.value === CUSTOM_RESULT;
            resultCustomBox.classList.toggle('hidden', !custom);
            resultValue.value = custom ? resultCustom.value.trim() : result.value;
            if (resultValue.value && !inspectedOn.value) {
                const now = new Date();
                inspectedOn.value = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
            }
        };
        result.addEventListener('change', () => { syncResult(); if (result.value === CUSTOM_RESULT) resultCustom.focus(); });
        resultCustom.addEventListener('input', syncResult);

        const row = el('div', {class: 'border border-gray-200 rounded-lg p-3', 'data-row': true}, [
            el('input', {type: 'hidden', name: `facilities[${key}][type]`, value: type}),
            masterInput,
            resultValue,
            // items-start: kolom "Hasil pemeriksaan" bisa lebih tinggi (isian "Lainnya"), kolom lain tetap sejajar di atas.
            el('div', {class: 'grid grid-cols-1 md:grid-cols-12 gap-3 items-start'}, [
                el('div', {class: 'md:col-span-3'}, [el('label', {class: 'block text-[11px] font-semibold text-gray-500 mb-1', text: isTransport ? 'Kendaraan / transportasi' : 'Perangkat'}), select]),
                el('div', {class: 'md:col-span-4'}, [el('label', {class: 'block text-[11px] font-semibold text-gray-500 mb-1', text: 'Keterangan di surat'}), description]),
                el('div', {class: 'md:col-span-3'}, [el('label', {class: 'block text-[11px] font-semibold text-gray-500 mb-1', text: 'Hasil pemeriksaan'}), result, resultCustomBox]),
                el('div', {class: 'md:col-span-2 flex items-end gap-1'}, [
                    el('div', {class: 'flex-1'}, [el('label', {class: 'block text-[11px] font-semibold text-gray-500 mb-1', text: 'Tgl periksa'}), inspectedOn]),
                    iconButton('delete', 'Hapus baris', e => e.currentTarget.closest('[data-row]').remove(), 'mb-1 hover:!text-red-600'),
                ]),
            ]),
            isTransport ? ownedRow : null,
        ]);
        document.getElementById(isTransport ? 'transportRows' : 'deviceRows').appendChild(row);
    }

    document.querySelectorAll('[data-add-facility]').forEach(btn => btn.addEventListener('click', () => addFacility(btn.dataset.addFacility)));

    // ---------- Penanda tangan ----------
    const signer = document.getElementById('signer_id');
    signer.addEventListener('change', () => {
        const option = signer.selectedOptions[0];
        if (!option || !option.value) return;
        document.getElementById('signer_name').value = option.dataset.name || '';
        const position = option.dataset.position || '';
        document.getElementById('signer_position').value = /direct|direk/i.test(position) ? 'Direktur' : position;
    });

    // ---------- Jadwal ----------
    const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const fmt = v => { const [y, m, d] = v.split('-'); return `${d} ${MONTHS[+m - 1]} ${y}`; };

    function updateSchedule() {
        scheduleAvailability();
        const dep = document.getElementById('departure_date').value;
        const start = document.getElementById('work_start_date').value;
        const ret = document.getElementById('return_date').value;
        const box = document.getElementById('schedulePreview');
        if (!dep || !start || !ret) { box.textContent = 'Isi ketiga tanggal untuk melihat kalimat di surat.'; return; }
        if (start < dep || ret < start) {
            box.textContent = 'Urutan tanggal harus: berangkat ≤ mulai bekerja ≤ kembali.';
            box.className = box.className.replace(/indigo/g, 'red');
            return;
        }
        box.className = box.className.replace(/red/g, 'indigo');
        const days = Math.round((new Date(ret) - new Date(start)) / 86400000) + 1;
        const first = dep === start
            ? `akan berangkat dan memulai pekerjaan pada ${fmt(dep)}`
            : `akan berangkat pada ${fmt(dep)}, memulai pekerjaan pada ${fmt(start)}`;
        box.textContent = `Yang bersangkutan ${first} dan akan kembali pada ${fmt(ret)} (${days} hari kerja).`;
    }

    const departure = document.getElementById('departure_date');
    const workStart = document.getElementById('work_start_date');
    let previousDeparture = departure.value;
    departure.addEventListener('change', () => {
        // Kebanyakan surat berangkat dan mulai bekerja di hari yang sama: selama "mulai bekerja"
        // masih sama dengan tanggal berangkat sebelumnya, ikut digeser.
        if (!workStart.value || workStart.value < departure.value || workStart.value === previousDeparture) {
            workStart.value = departure.value;
        }
        previousDeparture = departure.value;
        const ret = document.getElementById('return_date');
        if (!ret.value || ret.value < workStart.value) ret.value = workStart.value;
        updateSchedule();
    });
    ['work_start_date', 'return_date'].forEach(id => document.getElementById(id).addEventListener('change', updateSchedule));

    // ---------- Kolom paraf ----------
    // Sama dengan App\Support\AssignmentLetterInitialBoxes::boxes(): atasan level 3, lalu level 2,
    // satu kolom per orang, lalu HRD & GA HSE.
    function updateInitialBoxes() {
        const seen = new Set();
        const superiors = selectedIds().flatMap(id => byId.get(id)?.superiors || []).filter(s => !seen.has(s.id) && seen.add(s.id));
        const labels = [];
        [3, 2].forEach(level => superiors.filter(s => s.level === level).forEach(s => {
            labels.push(labels.includes(s.label) ? `${s.label} (${s.name})` : s.label);
        }));
        const boxes = labels.concat(FIXED_BOXES).join(', ');
        document.getElementById('initialBoxesAuto').textContent = boxes;
        if (!{{ $approvalRequired ? 'true' : 'false' }}) document.getElementById('initial_boxes').placeholder = boxes;
    }

    // ---------- Isi awal ----------
    (INITIAL.employees || []).forEach(row => addEmployee(row.employee_id, row.position));
    (INITIAL.destinations || []).forEach(row => addDestination(row));
    if (!destBox.children.length) addDestination();
    (INITIAL.facilities || []).forEach(row => addFacility(row.type === 'transport' ? 'transport' : 'device', row));
    renumberEmployees();
    updateSchedule();
})();
</script>
@endpush

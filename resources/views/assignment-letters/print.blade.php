<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Tugas {{ $letter->number ?? 'Draft' }}</title>
    <style>
        /* Meniru surat tugas Word HRD (A4, Times New Roman 12 pt) dengan tata letak lebih padat: isi
           bermargin 2 cm (Word 1 inci) supaya lebih sedikit baris terpotong. Kop surat melebar hampir
           selebar kertas (melewati margin isi), jadi margin halaman dibuat sempit dan isi surat diberi
           padding sendiri (.letter-body) selebar 170 mm. */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page { size: A4 portrait; margin: 8mm 8mm 15mm; }
        html { background: #e5e7eb; }
        /* Paragraf spasi tunggal tanpa jarak antarparagraf seperti Word; baris tabel dan jarak antarbagian
           sedikit lebih rapat daripada Word supaya surat panjang tetap muat satu halaman. */
        body { color: #000; font-family: "Times New Roman", Times, serif; font-size: 12pt; line-height: 1.15; }
        .no-print { position: fixed; top: 14px; z-index: 10; border: 0; border-radius: 5px; padding: 9px 14px; font: 700 12px Arial, sans-serif; text-decoration: none; cursor: pointer; }
        .back-btn { left: 14px; background: #374151; color: #fff; }
        .print-btn { right: 14px; background: #0f766e; color: #fff; }
        /* Di kiri tombol Cetak; muncul kalau hasil pindai surat bertanda tangan sudah diunggah. */
        .signed-btn { right: 84px; background: #4f46e5; color: #fff; }
        .sheet { position: relative; width: 210mm; min-height: 297mm; margin: 24px auto; padding: 8mm 8mm 15mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); overflow: hidden; }
        .letter-body { padding: 0 12mm; }
        table.page-layout { width: 100%; border-collapse: collapse; }
        table.page-layout > thead > tr > td, table.page-layout > tbody > tr > td { padding: 0; vertical-align: top; }
        /* Kop: logo ±7,1 cm di kiri, teks Courier New di kanannya (nama 16 pt tebal, alamat 10 pt). */
        /* Garis tebal 2,28 pt di bawah teks, dari tepi kanan logo sampai ujung kanan, sejajar bawah logo. */
        .kop { display: flex; align-items: stretch; margin: 0 0 3mm 5mm; }
        .kop-logo { width: 71mm; height: auto; display: block; flex-shrink: 0; align-self: flex-start; }
        .kop-text { flex: 1; font-family: "Courier New", Courier, monospace; padding: 1mm 0 1.5mm 3mm; border-bottom: 2.28pt solid #000; }
        .kop-name { font-size: 16pt; font-weight: 700; line-height: 1.1; word-spacing: -2px; }
        .kop-meta { font-size: 10pt; line-height: 1.2; margin-top: 1px; }
        .title { text-align: center; font-weight: 700; text-decoration: underline; }
        .number { text-align: center; margin-bottom: 6pt; }
        .date { text-align: right; }
        .justify { text-align: justify; }
        .indent { text-indent: 0.64cm; }
        /* Lebar kolom tetap (bukan menyesuaikan isi) supaya titik dua kedua tabel lurus juga di layar HP. */
        table.fields { border-collapse: collapse; width: 100%; table-layout: fixed; margin-bottom: 3pt; }
        table.fields td { padding: 0 3px; vertical-align: top; line-height: 1.2; overflow-wrap: anywhere; }
        .col-no { width: 0.8cm; }
        .col-label { width: 4.3cm; }
        .col-sep { width: 0.45cm; text-align: center; }
        /* = .col-no + .col-label, supaya titik dua penanda tangan lurus dengan titik dua daftar karyawan. */
        .signer .col-label { width: 5.1cm; }
        .destination { text-align: justify; }
        /* Nomor menggantung: baris kedua kalimat sejajar dengan teks, bukan dengan nomornya. */
        ol.destinations { padding-left: 0.5cm; }
        ol.destinations li { text-align: justify; }
        .sign-block { width: 45%; margin-left: auto; margin-top: 8pt; text-align: center; page-break-inside: avoid; }
        .sign-space { height: 38pt; }
        .sign-name { text-decoration: underline; }
        /* Seperti di Word: rata kanan di bawah tanda tangan, judul kolom abu-abu Calibri tebal. */
        table.paraf { border-collapse: collapse; margin: 6pt 0 0 auto; page-break-inside: avoid; font-family: Calibri, Carlito, Arial, sans-serif; }
        table.paraf td { border: 1px solid #000; min-width: 1.5cm; padding: 1px 5px; text-align: center; font-size: 8pt; line-height: 1.15; }
        table.paraf tr.head td { background: #d9d9d9; font-weight: 700; white-space: nowrap; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table.paraf tr.box td { height: 0.6cm; }
        /* Kotak berisi tanda tangan digital hasil approval: lebih tinggi supaya ttd terbaca. */
        table.paraf.digital tr.box td { height: 0.95cm; padding: 1px 3px; vertical-align: middle; }
        table.paraf img.paraf-img { max-height: 0.85cm; max-width: 2.2cm; display: block; margin: 0 auto; }
        table.paraf .paraf-name { font-size: 6.5pt; line-height: 1.1; }
        @media screen and (max-width: 840px) {
            .sheet { width: 100%; min-height: 0; margin: 0; padding: 64px 16px 24px; box-shadow: none; }
            .letter-body { padding: 0; font-size: 10.5pt; }
            .kop { margin-left: 0; }
            .kop-logo { width: 34%; }
            .kop-name { font-size: 11pt; }
            .kop-meta { font-size: 7.5pt; }
            .sign-block { width: 60%; }
            .col-label { width: 3.6cm; }
            .signer .col-label { width: 4.4cm; }
            table.paraf { max-width: 100%; }
            table.paraf td { min-width: 0; }
            table.paraf tr.head td { white-space: normal; }
        }
        .watermark { position: absolute; top: 42%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font: 700 96px Arial, sans-serif; color: rgba(220, 38, 38, .13); letter-spacing: 8px; pointer-events: none; white-space: nowrap; }
        @media print {
            html { background: #fff; }
            .no-print { display: none !important; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; overflow: visible; }
            .watermark { position: fixed; }
        }
    </style>
</head>
<body>
    {{-- Dibuka dari aplikasi mobile (tautan bertanda tangan) tidak ada halaman untuk kembali. --}}
    @if($backUrl)
        <a href="{{ $backUrl }}" class="back-btn no-print">Kembali</a>
    @endif
    <button type="button" onclick="window.print()" class="print-btn no-print">Cetak</button>
    @if(! empty($signedUrl))
        <a href="{{ $signedUrl }}" target="_blank" class="signed-btn no-print">Lihat yang sudah TTD</a>
    @endif

    @php
        $devices = $letter->facilities->where('type', 'device')->values();
        $transports = $letter->facilities->where('type', 'transport')->values();
        // Surat tanpa HT/kendaraan tetap mencetak barisnya dengan "-", seperti surat Word.
        $facilityRows = collect([
            ['label' => 'Perangkat Komunikasi', 'items' => $devices],
            ['label' => 'Transportasi', 'items' => $transports],
        ]);
    @endphp

    <main class="sheet">
        @if(! $letter->isIssued() && ! $letter->isCancelled())
            <div class="watermark">DRAFT</div>
        @elseif($letter->isCancelled())
            <div class="watermark">DIBATALKAN</div>
        @endif

        {{-- Seperti header Word: kop ditaruh di <thead> supaya browser mengulangnya di tiap halaman cetak. --}}
        <table class="page-layout">
        <thead><tr><td>
        <header class="kop">
            <img class="kop-logo" src="{{ asset('images/logo_be2.png') }}" alt="Beacon Engineering">
            <div class="kop-text">
                <div class="kop-name">PT. ARTA TEKNOLOGI COMUNINDO</div>
                <div class="kop-meta">Kadirojo 1, Purwomartani, Kec. Kalasan, Sleman, Daerah Istimewa Yogyakarta. Ph./Fax. (0274) 498 6899 e-mail : info@bejogja.com</div>
            </div>
        </header>
        </td></tr></thead>
        <tbody><tr><td>

        <div class="letter-body">
        <div class="title">SURAT TUGAS</div>
        {{-- Draft: perkiraan nomor, ditandai supaya tidak dikira nomor resmi (halaman juga ber-watermark DRAFT). --}}
        <div class="number">{{ $letter->number ?? ($letter->estimatedNumber() ?? '…').' (perkiraan)' }}</div>
        <div class="date">{{ $letter->city }}, {{ \App\Models\AssignmentLetter::formatDate($letter->letter_date) }}</div>

        <p>Saya yang bertandatangan di bawah ini :</p>
        <table class="fields signer">
            <tr><td class="col-label">Nama</td><td class="col-sep">:</td><td>{{ $letter->signer_name }}</td></tr>
            <tr><td class="col-label">Jabatan</td><td class="col-sep">:</td><td>{{ $letter->signer_position }}</td></tr>
            <tr><td class="col-label">Bertindak Atas Nama</td><td class="col-sep">:</td><td>{{ config('assignment_letters.company_name') }}</td></tr>
            <tr><td class="col-label">Alamat</td><td class="col-sep">:</td><td>{{ config('assignment_letters.company_address') }}</td></tr>
        </table>

        <p>Dengan ini memberikan {{ $letter->revises_letter_id ? 'perbaikan surat tugas '.$letter->revises?->number.' ' : 'surat tugas ' }}kepada karyawan kami :</p>
        <table class="fields">
            @foreach($letter->employees as $i => $row)
                <tr><td class="col-no">{{ $i + 1 }}.</td><td class="col-label">Nama</td><td class="col-sep">:</td><td>{{ $row->name }}</td></tr>
                <tr><td></td><td class="col-label">Nomor Karyawan</td><td class="col-sep">:</td><td>{{ $row->employee_code ?: '-' }}</td></tr>
                <tr><td></td><td class="col-label">Jabatan</td><td class="col-sep">:</td><td>{{ $row->position ?: '-' }}</td></tr>
            @endforeach
            @foreach($facilityRows as $group)
                @forelse($group['items'] as $item)
                    <tr><td></td><td class="col-label">{{ $group['label'] }}</td><td class="col-sep">:</td><td>{{ $item->description }}</td></tr>
                    <tr><td></td><td class="col-label">Hasil Pemeriksaan</td><td class="col-sep">:</td><td>{{ $item->inspectionLabel() }}</td></tr>
                @empty
                    <tr><td></td><td class="col-label">{{ $group['label'] }}</td><td class="col-sep">:</td><td>-</td></tr>
                    <tr><td></td><td class="col-label">Hasil Pemeriksaan</td><td class="col-sep">:</td><td>-</td></tr>
                @endforelse
            @endforeach
        </table>

        @if($purposeHeading = $letter->purposeHeading())
            <p class="justify">{{ $purposeHeading }}</p>
        @endif
        @php
            $destinationSentences = $letter->destinationSentences();
        @endphp
        {{-- Lebih dari satu tujuan diberi nomor; satu tujuan tetap berupa kalimat biasa seperti di Word. --}}
        @if(count($destinationSentences) > 1)
            <ol class="destinations">
                @foreach($destinationSentences as $sentence)
                    <li>{{ $sentence }}</li>
                @endforeach
            </ol>
        @else
            @foreach($destinationSentences as $sentence)
                <p class="destination">{{ $sentence }}</p>
            @endforeach
        @endif

        <p class="justify indent">
            {{ $letter->scheduleSentence() }}
            Demikian surat tugas ini kami buat agar dipergunakan sebagaimana mestinya dengan penuh tanggung jawab.
        </p>

        <div class="sign-block">
            <div>Hormat kami,</div>
            <div class="sign-space"></div>
            <div class="sign-name">{{ $letter->signer_name }}</div>
            <div>{{ $letter->signer_position }}</div>
        </div>

        @php
            $boxes = $letter->initialBoxCells();
            $hasDigital = collect($boxes)->contains(fn ($cell) => $cell['approval']?->isApproved());
        @endphp
        @if($boxes)
            <table class="paraf {{ $hasDigital ? 'digital' : '' }}">
                <tr class="head">
                    @foreach($boxes as $box)
                        <td>{{ $box['label'] }}</td>
                    @endforeach
                </tr>
                <tr class="box">
                    @foreach($boxes as $box)
                        @php $approval = $box['approval']; @endphp
                        <td>
                            {{-- Disetujui: ttd digital pemberi persetujuan (atau namanya bila belum punya ttd). Dilewati atau belum: kosong untuk paraf basah. --}}
                            @if($approval && $approval->isApproved())
                                @if($signature = $approval->signatureUrl())
                                    <img class="paraf-img" src="{{ $signature }}" alt="Paraf {{ $box['label'] }}">
                                @else
                                    <span class="paraf-name">{{ $approval->actor?->full_name ?? $approval->approver?->full_name }}</span>
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif
        </div>
        </td></tr></tbody>
        </table>
    </main>
</body>
</html>

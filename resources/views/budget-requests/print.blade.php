<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Pengajuan Anggaran - {{ $budgetRequest->title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page { size: A4 landscape; margin: 8mm 10mm; }
        body { background: #fff; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 10px; line-height: 1.2; }
        .no-print { position: fixed; top: 14px; z-index: 10; border: 0; border-radius: 5px; padding: 9px 14px; font: 700 12px Arial, sans-serif; text-decoration: none; cursor: pointer; }
        .back-btn { left: 14px; background: #374151; color: #fff; }
        .print-btn { right: 14px; background: #0f766e; color: #fff; }
        .sheet { width: 100%; max-width: 277mm; margin: 0 auto; }
        .kop-table, .info-table, .budget-table, .signature-table, .payment-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .kop-table td { border-bottom: 3px solid #000; padding: 4px 6px 5px; vertical-align: top; }
        .logo-cell { width: 150px; }
        .logo-cell img { width: 142px; height: auto; display: block; }
        .company-title { font-family: "Courier New", monospace; font-size: 17px; font-weight: 700; letter-spacing: .5px; }
        .company-meta { margin-top: 1px; font-family: "Courier New", monospace; font-size: 10px; line-height: 1.15; }
        .form-title { border-bottom: 1px solid #000; padding: 4px 0 5px; text-align: center; font-size: 15px; font-weight: 800; }
        .info-table td { border: 1px solid #000; height: 21px; padding: 2px 5px; vertical-align: middle; }
        .field-label { width: 92px; font-weight: 800; }
        .field-sep { width: 12px; text-align: center; }
        .required { color: #c00000; }
        .budget-table th, .budget-table td { border: 1px solid #000; height: 22px; padding: 2px 5px; vertical-align: middle; }
        .budget-table th { text-align: center; font-weight: 800; }
        .col-no { width: 42px; text-align: center; }
        .col-rincian { width: 57%; }
        .col-anggaran { width: 15%; text-align: right; }
        .col-keterangan { width: 23%; }
        .total-label { text-align: right; font-weight: 800; }
        .total-rp { width: 35px; text-align: left; font-weight: 800; }
        .total-value { text-align: right; font-weight: 800; }
        .signature-table td { height: 78px; padding: 0 16px; text-align: center; vertical-align: bottom; }
        .signature-role { height: 20px; padding-top: 8px; font-weight: 800; text-align: center; vertical-align: top !important; }
        .signature-date { margin-bottom: 22px; font-size: 11px; }
        .signature-line { border-bottom: 1px solid #000; height: 15px; margin: 0 auto 3px; max-width: 170px; }
        .signature-name { min-height: 13px; font-weight: 700; }
        .approval-heading { height: 20px !important; padding: 2px 0 0 !important; font-weight: 800; text-align: center; vertical-align: middle !important; }
        .payment-table td { height: 22px; padding: 2px 5px; }
        .payment-label { text-align: right; font-weight: 800; }
        .payment-line { border-bottom: 1px solid #000; }
        .footnote { margin-top: 4px; font-size: 10px; font-weight: 800; }
        @media print {
            .no-print { display: none !important; }
            .sheet { max-width: none; }
        }
    </style>
</head>
<body>
    <a href="{{ $backUrl }}" class="back-btn no-print">Kembali</a>
    <button type="button" onclick="window.print()" class="print-btn no-print">Cetak</button>

    @php
        $approvalChain = ($approvalChain ?? collect())->values();
        $signatureNames = [
            'pengaju' => $budgetRequest->employee?->full_name ?? '',
            'pj' => $approvalChain->get(0)?->approver?->full_name ?? '',
            'manager' => $approvalChain->get(1)?->approver?->full_name ?? '',
            'finance' => $approvalChain->get(2)?->approver?->full_name ?? '',
            'manager_admin' => $approvalChain->get(3)?->approver?->full_name ?? '',
            'direktur' => $approvalChain->get(4)?->approver?->full_name ?? '',
        ];
        $rows = max(10, $budgetRequest->items->count());
    @endphp

    <main class="sheet">
        <table class="kop-table">
            <tr>
                <td class="logo-cell">
                    <img src="{{ asset('images/logo_be2.png') }}" alt="Beacon Engineering">
                </td>
                <td>
                    <div class="company-title">PT. ARTA TEKNOLOGI COMUNINDO</div>
                    <div class="company-meta">
                        Kadirojo I, Purwomartani, Kec. Kalasan, Kab. Sleman, Daerah Istimewa Yogyakarta<br>
                        Ph./Fax. (0274) 498 6899, e-mail : info@bejogja.com
                    </div>
                </td>
            </tr>
        </table>

        <div class="form-title">FORM PENGAJUAN ANGGARAN PT. ARTA TEKNOLOGI COMUNINDO</div>

        <table class="info-table">
            <tr>
                <td class="field-label">Divisi<span class="required">*</span></td>
                <td class="field-sep">:</td>
                <td>{{ $budgetRequest->employee?->department?->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="field-label">Project<span class="required">*</span></td>
                <td class="field-sep">:</td>
                <td>{{ $budgetRequest->title }}</td>
            </tr>
            <tr>
                <td class="field-label">Keterangan<span class="required">*</span></td>
                <td class="field-sep">:</td>
                <td>{{ $budgetRequest->description ?: '-' }}</td>
            </tr>
        </table>

        <table class="budget-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-rincian">Rincian</th>
                    <th class="col-anggaran">Anggaran</th>
                    <th class="col-keterangan">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @for($i = 0; $i < $rows; $i++)
                    @php $item = $budgetRequest->items->get($i); @endphp
                    <tr>
                        <td class="col-no">{{ $i + 1 }}</td>
                        <td class="col-rincian">{{ $item?->type_label ?? '' }}</td>
                        <td class="col-anggaran">{{ $item ? number_format((float) $item->amount, 0, ',', '.') : '' }}</td>
                        <td class="col-keterangan">{{ $item?->description ?: '' }}</td>
                    </tr>
                @endfor
                <tr>
                    <td colspan="2" class="total-label">Total Anggaran</td>
                    <td class="total-rp">Rp {{ number_format((float) $budgetRequest->total_amount, 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <table class="signature-table">
            <tr>
                <td class="signature-role">Pengaju</td>
                <td class="signature-role">PJ/Leader</td>
                <td class="signature-role">Manager</td>
            </tr>
            <tr>
                <td>
                    <div class="signature-date">(__________/__________/__________)</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $signatureNames['pengaju'] }}</div>
                </td>
                <td>
                    <div class="signature-date">(__________/__________/__________)</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $signatureNames['pj'] }}</div>
                </td>
                <td>
                    <div class="signature-date">(__________/__________/__________)</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $signatureNames['manager'] }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="3" class="approval-heading">Mengetahui</td>
            </tr>
            <tr>
                <td class="signature-role">Finance</td>
                <td class="signature-role">Manager Admin</td>
                <td class="signature-role">Direktur</td>
            </tr>
            <tr>
                <td>
                    <div class="signature-date">(__________/__________/__________)</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $signatureNames['finance'] }}</div>
                </td>
                <td>
                    <div class="signature-date">(__________/__________/__________)</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $signatureNames['manager_admin'] }}</div>
                </td>
                <td>
                    <div class="signature-date">(__________/__________/__________)</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $signatureNames['direktur'] }}</div>
                </td>
            </tr>
        </table>

        <table class="payment-table">
            <tr>
                <td class="payment-label" style="width: 52%;">Pembayaran:</td>
                <td class="payment-line"></td>
            </tr>
        </table>

        <div class="footnote">Tanda* (Wajib diisi)</div>
    </main>
</body>
</html>

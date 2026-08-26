<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat {{ $years }} Tahun Bersama Kami</title>
</head>
<body style="margin:0; padding:0; background:#ffffff; font-family:Arial, Helvetica, sans-serif; color:#172033;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;">
        <tr>
            <td style="padding:0 32px;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f8fafc;">
                    <tr>
                        <td align="center" style="padding:26px 16px;">
                            <div style="font-size:18px; font-weight:800; color:#1e40af; letter-spacing:0;">HRIS Beacon</div>
                            <div style="margin-top:5px; font-size:11px; font-weight:600; color:#64748b;">{{ $company->name ?? config('app.name') }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:32px 24px 24px;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:520px;">
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <table cellpadding="0" cellspacing="0" role="presentation" style="background:#eef2ff; border-radius:12px;">
                                <tr>
                                    <td align="center" style="padding:20px 40px;">
                                        <div style="font-size:34px; font-weight:800; color:#1e40af; line-height:1.1;">{{ $years }}</div>
                                        <div style="margin-top:4px; font-size:12px; font-weight:700; color:#4f46e5; letter-spacing:1px;">TAHUN</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:15px; line-height:1.7;">
                            <p style="margin:0 0 18px; font-weight:700;">Hi {{ strtoupper($employee->full_name) }}</p>

                            <p style="margin:0 0 18px;">
                                Hari ini genap <strong>{{ $years }} tahun</strong> Anda bergabung bersama {{ $company->name ?? config('app.name') }}@if($joinLabel), terhitung sejak {{ $joinLabel }}@endif.
                            </p>

                            <p style="margin:0 0 18px;">
                                Terima kasih atas dedikasi, kerja keras, dan kontribusi Anda selama ini. Perjalanan perusahaan tidak akan sampai di titik ini tanpa orang-orang seperti Anda.
                            </p>

                            <p style="margin:0 0 22px;">Semoga tahun-tahun berikutnya membawa lebih banyak pencapaian, untuk Anda dan untuk kita semua.</p>

                            <p style="margin:0 0 2px;">Regards,</p>
                            <p style="margin:0 0 28px;">{{ $company->name ?? config('app.name') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding:0 32px;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f8fafc;">
                    <tr>
                        <td align="center" style="padding:28px 16px; color:#94a3b8; font-size:11px;">
                            &copy; {{ now()->year }} HRIS Beacon. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

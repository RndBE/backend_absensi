<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Akses Dashboard HRIS Beacon</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:28px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:22px 28px; text-align:center; background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                            <div style="font-size:18px; font-weight:700; color:#111827;">HRIS Beacon</div>
                            <div style="font-size:12px; color:#6b7280; margin-top:4px;">Employee Portal</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 18px; font-size:15px; font-weight:700;">Hi {{ strtoupper($employee->full_name) }}</p>
                            <p style="margin:0 0 18px; font-size:14px; line-height:1.6; color:#374151;">
                                Link akses dashboard employee Anda sudah tersedia. Klik tombol berikut untuk masuk ke dashboard HRIS Beacon.
                            </p>
                            <p style="margin:26px 0; text-align:center;">
                                <a href="{{ $magicUrl }}" style="display:inline-block; padding:12px 20px; background:#4f46e5; color:#ffffff; text-decoration:none; border-radius:8px; font-size:14px; font-weight:700;">
                                    Buka Dashboard
                                </a>
                            </p>
                            <p style="margin:0 0 16px; font-size:12px; line-height:1.6; color:#6b7280;">
                                Link ini berlaku sampai {{ $expiresAt->locale('id')->translatedFormat('d F Y H:i') }} dan hanya bisa digunakan satu kali.
                            </p>
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#6b7280;">
                                Jika Anda tidak meminta akses ini, abaikan email ini atau hubungi HR.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px; background:#f9fafb; border-top:1px solid #e5e7eb; text-align:center; font-size:11px; color:#9ca3af;">
                            &copy; {{ now()->year }} HRIS Beacon. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

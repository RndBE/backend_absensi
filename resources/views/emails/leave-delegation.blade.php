<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Delegasi Tugas</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:28px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
                    {{-- Header: logo perusahaan (kiri) + "Delegasi" (kanan, merah) --}}
                    <tr>
                        <td style="padding:24px 32px 18px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="left" style="vertical-align:middle;">
                                        @if($company && $company->logo)
                                            <img src="{{ asset('storage/'.$company->logo) }}" alt="{{ $company->name }}" style="max-height:42px; max-width:200px;">
                                        @else
                                            <span style="font-size:18px; font-weight:700; color:#111827;">{{ $company->name ?? 'HRIS Beacon' }}</span>
                                        @endif
                                    </td>
                                    <td align="right" style="vertical-align:middle; font-size:18px; font-weight:700; color:#dc2626;">
                                        Delegasi
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px;">
                            <div style="border-top:1px solid #e5e7eb;"></div>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <p style="margin:0 0 22px; font-size:14px; color:#374151;">
                                {{ strtoupper($delegate->full_name) }} yang terhormat,
                            </p>
                            <p style="margin:0 0 18px; font-size:14px; line-height:1.8; color:#374151;">
                                <strong>{{ $leave->employee->full_name ?? 'Rekan Anda' }}</strong> mendelegasikan tugasnya kepada Anda
                                selama menjalani {{ $leave->leaveType->name ?? 'cuti/izin' }}
                                mulai <strong>tanggal {{ $leave->start_date?->locale('id')->translatedFormat('d F Y') }}</strong>
                                hingga <strong>{{ ($leave->end_date ?? $leave->start_date)?->locale('id')->translatedFormat('d F Y') }}</strong>.
                                Pengajuan ini telah <strong>disetujui</strong>. Mohon bantuannya menangani tugas terkait selama periode tersebut.
                            </p>
                            <p style="margin:28px 0 0; font-size:14px; color:#374151;">
                                Selamat hari {{ now()->locale('id')->translatedFormat('l') }}!
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:22px 32px; text-align:center; font-size:11px; color:#9ca3af;">
                            &copy; {{ now()->year }} {{ $company->name ?? 'HRIS Beacon' }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

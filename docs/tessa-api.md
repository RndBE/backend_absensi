# Tessa API — Integrasi AI Kantor

API read-only + sebagian aksi untuk AI kantor **Tessa**. Payroll/slip gaji **tidak** dapat diakses.

## Autentikasi

Kirim API key di setiap request (salah satu):

```
Authorization: Bearer <TESSA_API_KEY>
```
atau
```
X-Api-Key: <TESSA_API_KEY>
```

Key disimpan di `.env` server: `TESSA_API_KEY`. Opsional `TESSA_COMPANY_ID` untuk membatasi Tessa ke satu perusahaan (kosong = semua).

Respons gagal auth: `401`. Bila integrasi belum dikonfigurasi: `503`.

Base URL: `https://<host>/api/tessa`

## Endpoint baca

| Method | Path | Keterangan |
|---|---|---|
| GET | `/ping` | Cek koneksi & daftar kapabilitas |
| GET | `/employees` | Daftar karyawan (tanpa gaji). Filter: `search`, `department_id`, `only_active`, `limit` |
| GET | `/employees/{id}` | Detail karyawan (tanpa gaji) |
| GET | `/attendance` | Presensi. Filter: `from`, `to`, `employee_id`, `status`, `late_only` |
| GET | `/attendance/recap` | Rekap kehadiran. Filter: `date` |
| GET | `/leaves` | Pengajuan cuti. Filter: `status`, `employee_id` |
| GET | `/overtimes` | Pengajuan lembur |
| GET | `/attendance-requests` | Pengajuan presensi |
| GET | `/budget-requests` | Pengajuan anggaran |
| GET | `/travel-reports` | LHP |
| GET | `/lpj` | LPJ |
| GET | `/approvals/summary` | Jumlah pengajuan pending per jenis |
| GET | `/company` | Info perusahaan |
| GET | `/announcements` | Feed notifikasi/pengumuman. Filter: `type` |

Daftar mendukung pagination: `?limit=` (default 50, maks 200) dan `?page=`.
Filter `status`: `pending`, `in_review`, `approved`, `rejected`.

## Aksi

| Method | Path | Body |
|---|---|---|
| POST | `/notifications` | `title`*, `message`*, lalu salah satu target: `employee_id` / `department_id` / `all=true`. Opsional `push=true` untuk kirim push FCM. |

Contoh:
```bash
curl -X POST https://<host>/api/tessa/notifications \
  -H "Authorization: Bearer <TESSA_API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{"title":"Pengingat LHP","message":"Mohon lengkapi LHP Anda.","employee_id":12,"push":true}'
```

## Yang TIDAK bisa diakses

- Payroll: payroll-runs, payroll-components, employee-payrolls, payroll-adjustments
- Slip gaji (payslips)
- Field gaji/finansial pada data karyawan (basic_salary, tunjangan, rekening, NIK/NPWP/BPJS)

Proteksi diterapkan di middleware `tessa.api` (`app/Http/Middleware/TessaApiKey.php`) + tidak ada endpoint payroll yang didaftarkan untuk Tessa.

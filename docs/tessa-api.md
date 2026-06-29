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
| GET | `/shifts` | Daftar shift yang valid (nama, jam, off/overnight) — referensi sebelum mengisi jadwal |

Daftar mendukung pagination: `?limit=` (default 50, maks 200) dan `?page=`.
Filter `status`: `pending`, `in_review`, `approved`, `rejected`.

## Aksi

| Method | Path | Body |
|---|---|---|
| POST | `/notifications` | `title`*, `message`*, lalu salah satu target: `employee_id` / `department_id` / `all=true`. Opsional `push=true` untuk kirim push FCM. |
| POST | `/schedules` | `assignments`* (array, maks 500 baris). Mengisi jadwal shift karyawan per tanggal. Mendukung `dry_run`. |
| POST | `/approvals/{type}/{id}/approve` | Setujui pengajuan. `type`: leave/overtime/attendance/budget/travel_report. Opsional `notes`, `as_employee_id`, `dry_run`. |
| POST | `/approvals/{type}/{id}/reject` | Tolak pengajuan (param sama). |
| POST | `/data-change-requests` | Usulkan ubah data karyawan: `employee\|employee_code\|employee_id` + `changes{field:value}`. Jadi pengajuan yang disetujui superadmin di website. |
| POST | `/shifts` | Buat master shift: `name`*, `start_time`, `end_time`, `is_off`, `is_overnight`, `work_hours`, `auto_overtime`. |
| PUT | `/shifts/{id}` | Edit master shift (field sama). |
| POST | `/schedule-templates` | Buat template mingguan: `name`*, `days[]` ({`day_of_week`:1-7, `shift`\|`shift_id`}). |
| POST | `/schedule-templates/assign` | Tempel template: `template_id`* + `employees[]`. |
| POST | `/requests/{type}` | Buat pengajuan atas nama karyawan. `type`: leave/overtime/attendance/budget/travel-report + `employee\|employee_code\|employee_id` + field sesuai jenisnya. |

### Model aktor & pengaman
- **Aktor**: aksi sensitif (approve/reject, usul perubahan data) dijalankan "atas nama" seorang **superadmin**. Default diambil dari `TESSA_ACTS_AS_EMPLOYEE_ID`, atau di-override per request via `as_employee_id`. Approve tercatat: disetujui oleh approver step + acted_by superadmin + catatan **"(via Tessa AI)"**.
- **Approve `dry_run`**: kirim `"dry_run":true` untuk melihat rencana tanpa mengeksekusi.
- **Ubah data karyawan TIDAK langsung** — selalu jadi pengajuan yang harus disetujui superadmin manusia di website (tab Perubahan Data). Field terkunci: `role`, `password`, `manager_id`, `approver_id`, `company_id` (dan semua hal payroll/gaji).
- **Approve perubahan data** sengaja TIDAK tersedia untuk Tessa — wajib lewat website.

Contoh kirim notifikasi:
```bash
curl -X POST https://<host>/api/tessa/notifications \
  -H "Authorization: Bearer <TESSA_API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{"title":"Pengingat LHP","message":"Mohon lengkapi LHP Anda.","employee_id":12,"push":true}'
```

### Mengisi jadwal (shift) — contoh "jadwal security"

Setiap baris di `assignments`: tentukan karyawan (salah satu dari `employee` nama / `employee_code` / `employee_id`), `date` (YYYY-MM-DD), dan shift (`shift` nama / `shift_id`). Opsional `notes`.

```bash
curl -X POST https://<host>/api/tessa/schedules \
  -H "Authorization: Bearer <TESSA_API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{"assignments":[
    {"employee":"Budi Santoso","date":"2026-07-01","shift":"Pagi"},
    {"employee_code":"SEC-001","date":"2026-07-01","shift":"Malam"},
    {"employee_id":12,"date":"2026-07-02","shift":"Off","notes":"libur"}
  ]}'
```

**Mode preview (`dry_run`)** — sangat disarankan untuk alur "kirim Excel → tinjau → simpan":
```bash
# 1. Preview dulu (TIDAK menyimpan apa pun)
curl -X POST https://<host>/api/tessa/schedules \
  -H "Authorization: Bearer <TESSA_API_KEY>" -H "Content-Type: application/json" \
  -d '{"dry_run":true,"assignments":[{"employee":"Budi","date":"2026-07-01","shift":"Pagi"}]}'
# Respons: action "would_create"/"would_update" + "current_shift" (shift saat ini)

# 2. Kalau sudah benar, kirim ulang TANPA dry_run untuk menyimpan.
```

Catatan:
- `dry_run: true` → hanya validasi & tampilkan rencana, **tidak menulis** ke database.
- Tanggal yang sama untuk karyawan yang sama akan **ditimpa** (1 shift per karyawan per hari). `current_shift` menunjukkan shift lama yang akan ditimpa.
- Bila nama shift ada lebih dari satu (mis. dua "Pagi" dengan jam beda), API menolak baris itu dan meminta `shift_id` — ambil id dari `GET /shifts`.
- Respons mengembalikan hasil **per baris** (`results[]`) berisi `success` + `action` (created/updated/would_create/would_update) atau `error`, plus ringkasan `valid` & `failed`. Sebagian baris bisa berhasil meski lainnya gagal.

## Yang TIDAK bisa diakses

- Payroll: payroll-runs, payroll-components, employee-payrolls, payroll-adjustments
- Slip gaji (payslips)
- Field gaji/finansial pada data karyawan (basic_salary, tunjangan, rekening, NIK/NPWP/BPJS)

Proteksi diterapkan di middleware `tessa.api` (`app/Http/Middleware/TessaApiKey.php`) + tidak ada endpoint payroll yang didaftarkan untuk Tessa.

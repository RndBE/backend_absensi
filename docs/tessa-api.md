# Tessa API — Integrasi AI Kantor

API baca + sebagian aksi untuk AI kantor **Tessa**, yang **MENGIKUTI role HRIS**. Payroll/slip gaji **tidak** dapat diakses (apa pun role-nya).

Base URL: `https://<host>/api/tessa`

## Autentikasi — 2 langkah

Tessa TIDAK punya role sendiri. Aktor = karyawan pemilik token, dan kapabilitasnya PERSIS mengikuti role HRIS orang itu.

**1. Service key** membuktikan pemanggil adalah server Tessa. Dipakai HANYA untuk `/ping` & `/session`:
```
X-Api-Key: <TESSA_API_KEY>          (atau Authorization: Bearer <TESSA_API_KEY>)
```

**2. Kenali karyawan → token per-user.** Dua cara (keduanya dijaga service key):

Berdasarkan **nomor HP** (untuk bot WhatsApp — cocokkan ke `employees.phone`):
```bash
curl -X POST https://<host>/api/tessa/session \
  -H "X-Api-Key: <TESSA_API_KEY>" -H "Content-Type: application/json" \
  -d '{"phone":"+62812xxxx"}'
```
Atau berdasarkan **kredensial** (email + kata sandi):
```bash
curl -X POST https://<host>/api/tessa/session \
  -H "X-Api-Key: <TESSA_API_KEY>" -H "Content-Type: application/json" \
  -d '{"email":"budi@perusahaan.com","password":"••••••"}'
```
Respons: `{ "success":true, "token":"<token>", "employee":{...,"role":"..."}, "is_admin":true|false }`.
`is_admin=false` (role employee) → hanya self-service & data sendiri. `is_admin=true` → sesuai permission-nya.

Nomor HP dinormalkan otomatis (`0812…`, `62812…`, `+62812…` dianggap sama). Nomor tak terdaftar → `404`; terdaftar ganda → `409`.

**3. Semua endpoint lain** memakai token karyawan itu:
```
Authorization: Bearer <token dari /session>
```
Identitas & role terikat ke token — **tidak bisa dipalsukan / naik pangkat**. Logout: `POST /session/logout`.

Key `.env`: `TESSA_API_KEY` (service key), opsional `TESSA_COMPANY_ID` (batasi ke satu perusahaan).

Respons: gagal service key `401`/belum dikonfigurasi `503`; token tidak valid `401`; role tak mengizinkan `403`.

## Endpoint baca

| Method | Path | Keterangan |
|---|---|---|
| GET | `/ping` | Cek koneksi & daftar kapabilitas |
| GET | `/employees` | Daftar karyawan (tanpa gaji). Filter: `search`, `department_id`, `only_active`, `limit` |
| GET | `/employees/{id}` | Detail karyawan (tanpa gaji) |
| GET | `/attendance` | Presensi. Filter: `from`, `to`, `employee_id`, `status`, `late_only` |
| GET | `/attendance/recap` | Rekap kehadiran. Filter: `date` |
| GET | `/schedules` | Jadwal kerja. Filter: `from`, `to`, `employee_id` |
| GET | `/leave-balances` | Saldo cuti. Filter: `year`, `employee_id` |
| GET | `/leaves` | Pengajuan cuti. Filter: `status`, `employee_id` |
| GET | `/overtimes` | Pengajuan lembur |
| GET | `/attendance-requests` | Pengajuan presensi |
| GET | `/budget-requests` | Pengajuan anggaran |
| GET | `/travel-reports` | LHP |
| GET | `/lpj` | LPJ |
| GET | `/approvals/summary` | Jumlah pengajuan pending per jenis |
| GET | `/approvals/{type}/{id}/next` | Approver step aktif / status final untuk pengajuan. `type`: leave/overtime/attendance/budget/travel_report |
| GET | `/company` | Info perusahaan |
| GET | `/announcements` | Feed notifikasi/pengumuman. Filter: `type` |
| GET | `/shifts` | Daftar shift yang valid (nama, jam, off/overnight) — referensi sebelum mengisi jadwal |

Daftar mendukung pagination: `?limit=` (default 50, maks 200) dan `?page=`.
Filter `status`: `pending`, `in_review`, `approved`, `rejected`.

**Cakupan sesuai role:**
- **Non-admin (role employee):** semua daftar & profil DIPAKSA hanya ke datanya sendiri (`/employees` hanya dirinya, `/attendance`/`/leaves`/dst hanya miliknya, `/announcements` hanya notifikasinya).
- **Admin (role selain employee):** melihat sesuai scope perusahaannya + filter `employee_id`.
- `/attendance/recap` & `/approvals/summary` (agregat se-perusahaan): **admin saja** (butuh `attendance.view` / `approvals.view`).

## Aksi

Tiap aksi dicek terhadap **permission role HRIS** aktor (resolver yang sama dengan website). Role yang tak punya izin → `403`.

| Method | Path | Permission | Body |
|---|---|---|---|
| POST | `/notifications` | `company.manage` | `title`*, `message`*, lalu salah satu target: `employee_id` / `department_id` / `all=true`. Opsional `push=true`. |
| POST | `/schedules` | `schedule.manage` | `assignments`* (array, maks 500 baris). Mengisi jadwal shift per tanggal. Mendukung `dry_run`. |
| POST | `/schedules/import` | `schedule.manage` | Upload file jadwal Excel/CSV multipart field `file`. Mendukung `dry_run`. PDF ditolak dengan pesan jelas sampai parser PDF/OCR tersedia. |
| POST | `/approvals/{type}/{id}/approve` | approver step (chain) | Setujui pengajuan. `type`: leave/overtime/attendance/budget/travel_report. Opsional `notes`, `dry_run`. Otorisasi = approver step aktif / superadmin (via Api\ApprovalController), sama seperti mobile — approver ber-role employee pun bisa. |
| POST | `/approvals/{type}/{id}/reject` | approver step (chain) | Tolak pengajuan (param sama). |
| PUT | `/requests/{type}/{id}` | self / create-perm | Edit pengajuan yang masih `pending`. `type`: leave/overtime/attendance. Employee hanya miliknya sendiri; edit punya orang lain butuh permission jenis terkait. |
| POST | `/data-change-requests` | `employees.update` | Usulkan ubah data karyawan: `employee\|employee_code\|employee_id` + `changes{field:value}`. Jadi pengajuan yang disetujui superadmin di website. |
| POST | `/shifts` | `schedule.master.manage` | Buat master shift: `name`*, `start_time`, `end_time`, `is_off`, `is_overnight`, `work_hours`, `auto_overtime`. |
| PUT | `/shifts/{id}` | `schedule.master.manage` | Edit master shift (field sama). |
| POST | `/schedule-templates` | `schedule.master.manage` | Buat template mingguan: `name`*, `days[]` ({`day_of_week`:1-7, `shift`\|`shift_id`}). |
| POST | `/schedule-templates/assign` | `schedule.master.manage` | Tempel template: `template_id`* + `employees[]`. |
| POST | `/requests/{type}` | self / create-perm | Buat pengajuan. `type`: leave/overtime/attendance/budget/travel-report. Tanpa ref karyawan = untuk **diri sendiri** (boleh employee). Untuk **orang lain** butuh permission jenis terkait (mis. `leaves.create`, `budget.manage`). |

## Format payload pengajuan (`POST /requests/{type}`)

Endpoint ini mendelegasikan ke `store()` yang **sama** dengan portal/mobile, jadi record di DB **identik** — asalkan Tessa mengirim field dengan **format yang benar**. Tiga aturan wajib:

- **Tanggal** → `YYYY-MM-DD` (mis. "besok"/"10 Juli" harus diubah Tessa jadi `2026-07-10`).
- **Jam** → `HH:mm` 24 jam (mis. `14:30`).
- **Durasi** → **angka menit** (mis. "2 jam" → `120`), bukan teks.

Tanpa ref karyawan = untuk diri sendiri. Field per jenis:

| type | Field wajib | Field opsional (format) |
|---|---|---|
| `leave` | `leave_type_id` (int), `start_date`, `end_date`, `total_days` (≥0.5), `reason` | `delegate_to` (id) |
| `overtime` | `date`, `reason` | `overtime_type` (`workday`\|`holiday`), `duration` (menit) **atau** `pre_shift_duration`/`post_shift_duration` (menit) + `planned_start`/`planned_end` (`HH:mm`), `break_duration` (menit) |
| `attendance` | `date`, `reason` | `clock_in`/`clock_out` (`HH:mm`) |
| `budget` | `type` (`budget`\|`reimbursement`), `title`, `items[]` ({`type`,`amount`}) | `description`, `surat_tugas_no`, `surat_tugas_date`, `distance_km`, `participants[]` |
| `travel-report` | `destination_city`, `departure_date`, `return_date`, `purpose`, `conclusion`, `activities` | `budget_request_id`, `surat_tugas_no`, `surat_tugas_date`, `distance_km` |

Contoh lembur (untuk diri sendiri, 2 jam pada 10 Juli):
```bash
curl -X POST https://<host>/api/tessa/requests/overtime \
  -H "Authorization: Bearer <token-karyawan>" -H "Content-Type: application/json" \
  -d '{"date":"2026-07-10","overtime_type":"workday","duration":120,"reason":"Kejar target rilis"}'
```
Kalau field format-nya salah (mis. `duration:"2 jam"` atau `date:"besok"`), `store()` akan menolak dengan `422` (validasi sama seperti portal) — jadi tidak akan tersimpan dengan format berbeda.

**Jejak sumber**: pengajuan lewat Tessa otomatis ditandai — suffix `(via Tessa)` pada field teksnya (`reason` untuk cuti/lembur/presensi, `description` untuk anggaran, `purpose` untuk LHP) agar approver/admin tahu asalnya. **Catatan**: lampiran/file tak bisa dikirim lewat Tessa (JSON/WhatsApp), jadi pengajuan via Tessa tersimpan tanpa lampiran; field lain identik dengan portal.

## Self-service tambahan

Saldo cuti:
```bash
curl https://<host>/api/tessa/leave-balances?year=2026 \
  -H "Authorization: Bearer <token-karyawan>"
```

Jadwal kerja pribadi:
```bash
curl "https://<host>/api/tessa/schedules?from=2026-07-06&to=2026-07-12" \
  -H "Authorization: Bearer <token-karyawan>"
```

Cek approver step aktif:
```bash
curl https://<host>/api/tessa/approvals/overtime/45/next \
  -H "Authorization: Bearer <token-karyawan>"
```

Edit pengajuan pending via Tessa:
```bash
curl -X PUT https://<host>/api/tessa/requests/overtime/45 \
  -H "Authorization: Bearer <token-karyawan>" -H "Content-Type: application/json" \
  -d '{"date":"2026-07-10","post_shift_duration":120,"post_shift_break":15,"reason":"Revisi durasi lembur"}'
```
- Edit hanya untuk status `pending`; yang sudah `in_review`/`approved`/`rejected` ditolak.
- Tipe edit yang didukung: `leave`, `overtime`, `attendance`. `budget` dan `travel-report` sengaja belum diedit via chat karena struktur item/aktivitasnya lebih kompleks.
- Employee biasa dipaksa hanya data dirinya sendiri; admin mengikuti permission HRIS.

## Reminder sistem (Tessa kirim via WhatsApp)

Endpoint **sistem** (dijaga **service key**, bukan token per-user — ini fungsi broadcast, bukan aksi milik satu karyawan):

```
GET /api/tessa/reminders/due?type=clockin|lhp|lpj[&date=YYYY-MM-DD]
Header: X-Api-Key: <TESSA_API_KEY>
```
Balasan:
```json
{
  "success": true, "type": "lpj", "date": "2026-07-03",
  "count": 2, "skipped_no_phone": 1,
  "reminders": [
    { "employee_id": 5, "name": "Budi", "phone": "0812...", "title": "Pengingat LPJ", "message": "Halo Budi, jangan lupa..." }
  ]
}
```
- **Read-only, berbasis state**: `lpj`/`lhp` = yang belum membuat LPJ/LHP pada pemicunya; `clockin` = terjadwal kerja hari ini tapi belum clock-in.
- ⚠️ **`type=clockin` JANGAN dipoll Tessa lagi.** Backend punya scheduler `clockin:remind` (tiap menit) yang mengirim sendiri: WhatsApp gateway + notifikasi in-app + push FCM. Kalau Tessa ikut mengirim, karyawan menerima **pesan dobel**. Endpoint ini dipertahankan untuk audit/kanal lain. Poll `lpj`/`lhp` tetap seperti biasa.
- **Cara pakai `lpj`/`lhp`**: scheduler Tessa panggil **sekali** pada jam yang diinginkan, lalu kirim tiap `message` ke `phone`-nya. `title`/`message` sudah siap kirim.
- **Jendela waktu `clockin`**: baris hanya muncul bila `jam masuk shift − clockin_reminder_before` jatuh di rentang `(since, now]` (tanpa `since` → lookback 30 menit). Karena itu `date` di masa lampau **selalu** mengembalikan `count: 0` — jendelanya dibandingkan ke **jam sekarang**, bukan ke tanggal yang diminta. Endpoint ini tidak bisa dipakai untuk backfill/verifikasi tanggal lampau.
- **Sumber jadwal `clockin`** (sama dengan `App\Support\ScheduledWorkingDays`): override `schedule_assignments` menang lebih dulu — termasuk menang atas hari libur, mis. security yang tetap masuk saat tanggal merah; lalu hari libur membatalkan; sisanya dari template mingguan `employees.schedule_template_id`. Karyawan tanpa ketiganya tak punya jam masuk, sehingga tidak pernah diingatkan.
- Menghormati toggle di Pengaturan Presensi (`*_reminder_enabled`) & `TESSA_COMPANY_ID`. Yang tanpa nomor HP dilaporkan di `skipped_no_phone`.
- Tidak menyentuh payroll. Ini **melengkapi** notifikasi in-app/FCM, bukan menggantikan.

## Notifikasi approver (Tessa WA approver step aktif)

Melengkapi alur approval: Tessa tahu **siapa approver yang harus di-WA** di tiap step.

```
GET /api/tessa/approvals/pending?type=leave|overtime|attendance|budget|travel_report[&since=ISO8601]
Header: X-Api-Key: <TESSA_API_KEY>
```
Balas pengajuan `pending`/`in_review` + **approver step aktif** (nama, nomor, pesan siap kirim):
```json
{ "count": 1, "skipped_no_phone": 0,
  "pending": [ {
    "type": "overtime", "id": 45, "employee": "Shandy", "current_step": 1,
    "approver": { "id": 7, "name": "Fadel", "phone": "0812..." },
    "message": "Ada pengajuan Lembur dari Shandy menunggu persetujuan Anda (step 1). Balas \"approve\" untuk menyetujui atau \"tolak\" untuk menolak."
  } ] }
```
- **Approver step aktif** dihitung dari chain `employee_approvers`. Begitu satu step di-approve, endpoint otomatis mengembalikan **approver step berikutnya** (Fadel → Nofiyanto → Ariyanto).
- **Dedup poll**: kirim `?since=<waktu poll terakhir>` → hanya yang berubah (pengajuan baru + yang baru maju step). Tanpa `since` = snapshot semua yang pending.
- **Alur**: Tessa poll → WA tiap `approver.phone` isi `message` → approver balas "approve"/"tolak" → Tessa panggil `POST /approvals/{type}/{id}/approve|reject` pakai **token approver itu** (chain memverifikasi dia approver step aktif).
- Tanpa nomor HP → tak bisa di-WA, dihitung di `skipped_no_phone`.

## Notifikasi pengaju (Tessa WA hasil approval final)

Setelah approval selesai final, Tessa bisa memberi tahu pengaju apakah pengajuannya disetujui atau ditolak:

```
GET /api/tessa/approvals/results?type=leave|overtime|attendance|budget|travel_report[&status=approved|rejected][&since=ISO8601]
Header: X-Api-Key: <TESSA_API_KEY>
```
Balas pengajuan yang statusnya sudah `approved` / `rejected` + nomor HP pengaju + pesan siap kirim:
```json
{ "count": 1, "skipped_no_phone": 0,
  "results": [ {
    "type": "overtime", "id": 45, "status": "approved",
    "employee": { "id": 12, "name": "Shandy", "phone": "0812..." },
    "title": "Pengajuan Lembur Disetujui",
    "message": "Halo Shandy, pengajuan Lembur Anda telah disetujui.",
    "updated_at": "2026-07-03T09:00:00.000000Z"
  } ] }
```
- **Hanya keputusan final**: `approved` dan `rejected`. Status `pending` / `in_review` tetap lewat `/approvals/pending` untuk WA approver berikutnya.
- **Dedup poll**: kirim `?since=<waktu poll terakhir>` agar Tessa hanya mengirim hasil yang baru berubah sejak poll terakhir.
- **Alur**: approver approve/reject → status pengajuan final `approved`/`rejected` → Tessa poll endpoint ini → WA `employee.phone` isi `message`.
- Tanpa nomor HP pengaju → tak bisa di-WA, dihitung di `skipped_no_phone`.

### Model aktor & pengaman
- **Aktor = karyawan pemilik token** (hasil `/session`), bukan superadmin. Semua aksi dijalankan atas namanya, dan **kapabilitas mengikuti role HRIS-nya** (resolver `AdminPermission`, sama dengan website). Tidak ada `as_employee_id` — identitas terikat token, tak bisa dipalsukan.
- **Approve/Reject**: TANPA gate permission khusus — otorisasi mengikuti **approval chain** (`employee_approvers`): hanya **approver step aktif** (atau superadmin) yang boleh, ditegakkan oleh `Api\ApprovalController`. Sama seperti mobile: approver ber-role employee (mis. team lead) pun bisa. Multi-step maju otomatis ke approver berikutnya. Tercatat: approver step + acted_by user + catatan **"(via Tessa AI)"**. `"dry_run":true` untuk melihat rencana tanpa eksekusi.
- **Employee vs admin**: role employee hanya self-service & data sendiri; role lain sesuai permission-nya. "Superadmin di Tessa" TIDAK berarti apa-apa — yang menentukan hanya role HRIS.
- **Ubah data karyawan TIDAK langsung** — selalu jadi pengajuan yang disetujui superadmin manusia di website (tab Perubahan Data). Field terkunci: `role`, `password`, `manager_id`, `approver_id`, `company_id` (dan semua hal payroll/gaji).
- **Payroll/slip gaji**: diblokir total di middleware, apa pun role-nya.

Contoh kirim notifikasi (pakai token karyawan dari `/session`):
```bash
curl -X POST https://<host>/api/tessa/notifications \
  -H "Authorization: Bearer <token-karyawan>" \
  -H "Content-Type: application/json" \
  -d '{"title":"Pengingat LHP","message":"Mohon lengkapi LHP Anda.","employee_id":12,"push":true}'
```

### Mengisi jadwal (shift) — contoh "jadwal security"

Setiap baris di `assignments`: tentukan karyawan (salah satu dari `employee` nama / `employee_code` / `employee_id`), `date` (YYYY-MM-DD), dan shift (`shift` nama / `shift_id`). Opsional `notes`.

```bash
curl -X POST https://<host>/api/tessa/schedules \
  -H "Authorization: Bearer <token-karyawan>" \
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
  -H "Authorization: Bearer <token-karyawan>" -H "Content-Type: application/json" \
  -d '{"dry_run":true,"assignments":[{"employee":"Budi","date":"2026-07-01","shift":"Pagi"}]}'
# Respons: action "would_create"/"would_update" + "current_shift" (shift saat ini)

# 2. Kalau sudah benar, kirim ulang TANPA dry_run untuk menyimpan.
```

### Import jadwal dari Excel/CSV via Tessa

Endpoint import membaca sheet pertama lalu mengubah tiap baris menjadi payload `assignments` yang sama dengan `POST /schedules`.

Header kolom yang dikenali:

| Field jadwal | Header yang diterima |
|---|---|
| Kode karyawan | `employee_code`, `kode karyawan`, `kode pegawai`, `nik`, `no induk` |
| Nama karyawan | `employee`, `nama`, `nama karyawan`, `karyawan` |
| ID karyawan | `employee_id`, `id karyawan` |
| Tanggal | `date`, `tanggal`, `tgl` |
| Shift | `shift`, `jadwal`, `nama shift`, `shift kerja` |
| ID shift | `shift_id`, `id shift` |
| Catatan | `notes`, `catatan`, `keterangan` |

Contoh file:

| employee_code | date | shift | notes |
|---|---|---|---|
| SEC-001 | 2026-07-06 | Malam | Pos 1 |
| SEC-002 | 2026-07-06 | Pagi | Pos 2 |

Preview dari file Excel:
```bash
curl -X POST https://<host>/api/tessa/schedules/import \
  -H "Authorization: Bearer <token-karyawan>" \
  -F "dry_run=1" \
  -F "file=@jadwal-security.xlsx"
```

Simpan setelah hasil preview benar:
```bash
curl -X POST https://<host>/api/tessa/schedules/import \
  -H "Authorization: Bearer <token-karyawan>" \
  -F "file=@jadwal-security.xlsx"
```

Catatan import:
- Format yang didukung saat ini: `.xlsx`, `.xls`, `.csv`.
- PDF akan mendapat `422` dengan pesan "PDF belum bisa diparse otomatis"; untuk PDF scan perlu tahap OCR/parser tambahan.
- Hasil respons tetap per baris (`results[]`) dan memakai aturan yang sama: nama karyawan/shift ambigu akan diminta pakai `employee_code`, `employee_id`, atau `shift_id`.

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

# Desain: Hapus Jaminan Pensiun (JP) dari Perhitungan Payroll

**Tanggal:** 2026-06-18
**Status:** Disetujui (menunggu review spec)

## Tujuan

Menghilangkan komponen Jaminan Pensiun (JP / BPJS Ketenagakerjaan Jaminan Pensiun)
dari sistem payroll secara **fungsional**: JP tidak lagi muncul di perhitungan
payroll run baru, slip gaji, bukti potong (untuk periode baru), dan UI. Data
konfigurasi (setting/migrasi/seeder) dan data historis tersimpan **tidak diubah**.

## Keputusan yang Sudah Disepakati

1. **Cara hapus:** Hapus dari perhitungan (fungsional), bukan sekadar nonaktif
   via setting, dan bukan hapus total dari kode/migrasi.
2. **Data lama:** Payroll run yang sudah dibuat dibiarkan apa adanya. Hanya
   payroll run **baru** yang tidak lagi mengandung JP.
3. **Setting/migrasi/seeder:** Tidak disentuh. Tabel `bpjs_settings`, key
   `jp_rate` & `jp_cap`, dan seeder tetap ada.
4. **Trade-off display historis:** Disetujui. Karena bagian Benefits "JP
   Perusahaan" di slip **dihitung ulang** (bukan dari data tersimpan), slip
   periode lama pun tidak akan lagi menampilkan baris JP Perusahaan. Angka net
   gaji, potongan karyawan tersimpan, dan bukti potong historis tetap utuh.
5. **Efek pajak:** Disetujui & dipahami. Iuran JP Karyawan (1%) selama ini
   menjadi *pengurang penghasilan bruto*. Setelah JP dihapus, payroll run baru
   tidak lagi mengurangkan JP sehingga basis pajak naik ~1% × gaji pokok dan
   PPh21 karyawan naik sedikit. Ini perilaku yang benar.
6. **Metode pajak:** Logika TER (Jan-Nov) dan rekalkulasi progresif bulan
   terakhir (Desember) **wajib tetap utuh**. Perubahan hanya pada nilai
   `bpjsEmployee` yang mengecil, bukan pada percabangan metode.

## Temuan Arsitektur (Penting)

Sistem terbelah dua untuk JP:

- **Sisi potongan karyawan ("JP Karyawan")** → disuntik sebagai komponen
  **tersimpan** pada payroll run detail (`PayrollRunController`). Net gaji &
  bukti potong periode lama membaca data tersimpan ini.
- **Sisi iuran perusahaan ("JP Perusahaan") di Benefits slip** → **dihitung
  ulang** saat render via `BpjsCalculator` (bukan dari data tersimpan).
- **Tax base** (`bpjs['employee_total']`) juga **dihitung ulang** via
  `BpjsCalculator` saat generate payroll run, bukan dari komponen tersimpan.
- **Bukti potong** (`TaxController::isEmployeeBpjsDeduction`) membaca komponen
  **tersimpan** via regex `/\b(jht|jkk|jkm|jp)\b/i`.

Implikasi: membuang JP dari `BpjsCalculator` otomatis menghilangkan JP dari
seluruh jalur yang dihitung-ulang (potongan baru, display benefits, tax base).
Data finansial historis yang tersimpan tetap aman karena tidak dihitung ulang.

## Perubahan

### 1. `app/Services/BpjsCalculator.php`
- Hapus blok perhitungan JP (baris ~68-75): variabel `$jpCap`, `$jpBasis`,
  `$jpCompany`, `$jpEmployee`, dan `$result['jp']`.
- Hapus `jp_rate` dari loop loader rate (baris 19) dan `jp_cap` dari loop loader
  cap (baris 24).
- `company_total`, `employee_total`, `grand_total` otomatis tidak lagi memuat JP.

### 2. `app/Http/Controllers/Admin/PayrollRunController.php`
- Hapus injeksi "JP Karyawan" (deduction) & "JP Perusahaan" (info) di blok
  injeksi BPJS (±292-323).
- Hapus generasi komponen JP Karyawan & JP Perusahaan di blok detail (±647-679).
- Pastikan tidak ada referensi `$bpjs['jp']` yang tersisa (akan jadi undefined
  index setelah `BpjsCalculator` tidak lagi menghasilkan key `jp`).
- Tax calc tetap memanggil `calculateMonthly`/`calculateDecember` apa adanya;
  hanya `$bpjs['employee_total']` yang otomatis mengecil.

### 3. Display slip — hapus referensi JP di `buildBpjsData()`
- `app/Http/Controllers/Admin/PayslipController.php` (baris 118, 137-139)
- `app/Http/Controllers/Api/PayslipController.php` (baris 160, 178-179)
- `app/Jobs/SendPayslipEmailJob.php` (baris 88, 106-107)
- Update guard `$tkHasContrib` agar tidak menjumlahkan `$bpjs['jp']['company']`.

### 4. UI
- `resources/views/admin/tax/settings.blade.php` (baris 184): hapus entri
  `jp_rate` dari array label BPJS.
- `resources/views/admin/tax/simulator.blade.php` (baris 108): hapus `'jp' =>
  'JP'` dari loop tampilan.

### 5. `app/Http/Controllers/Admin/TaxController.php`
- **Pertahankan** regex `/\b(jht|jkk|jkm|jp)\b/i` (baris 453) agar bukti potong
  periode lama yang punya komponen tersimpan "JP Karyawan" tetap dijumlahkan
  benar. Tidak ada perubahan di file ini.

### 6. Tidak disentuh
- `database/migrations/2026_03_29_060001_create_tax_bpjs_tables.php`
- `database/seeders/TaxBpjsSeeder.php`
- Tabel `bpjs_settings`, model `BpjsSetting`.

## Testing

- `tests/Feature/TaxCertificateGenerationTest.php`: tetap valid (komponen
  tersimpan "JP Karyawan" + regex dipertahankan). Verifikasi tetap hijau.
- Tambah test: payroll run baru yang digenerate **tidak** memunculkan komponen
  "JP Karyawan" / "JP Perusahaan".
- Tambah/sesuaikan test `BpjsCalculator`: hasil `calculate()` tidak lagi memiliki
  key `jp`; `employee_total`/`company_total` tidak memuat kontribusi JP.
- (Opsional) test bahwa PPh21 dihitung tanpa pengurang JP, dan metode tetap TER
  untuk Jan-Nov serta progresif untuk Desember.

## Risiko & Mitigasi

- **Undefined index `$bpjs['jp']`**: pastikan SEMUA reader dihapus/diupdate
  (langkah 2 & 3). Mitigasi: grep `['jp']` & `\"jp\"` di seluruh app setelah edit.
- **Slip historis kehilangan baris JP Perusahaan**: sudah disetujui sebagai
  trade-off; angka finansial tidak berubah.
- **Kenaikan PPh21 di run baru**: sudah disetujui sebagai perilaku benar.

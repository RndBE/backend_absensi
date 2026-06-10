# Audit Perhitungan PPh 21 — Kesesuaian dengan Tarif Efektif Rata-rata (TER) 2024

**Tanggal audit:** 10 Juni 2026
**Ruang lingkup:** Logika perhitungan pajak penghasilan (PPh 21) pada modul payroll
**Dasar hukum acuan:** PP No. 58 Tahun 2023 & PMK No. 168/PMK.03/2023 (berlaku 1 Januari 2024), UU HPP No. 7 Tahun 2021

---

> ## ✅ STATUS PERBAIKAN (10 Juni 2026)
> Temuan di bawah ini **SUDAH DIPERBAIKI**. Metode TER kini diimplementasikan:
> - Tabel TER lengkap (44+40+41 = 125 baris bulanan) ditambahkan via `Pph21Calculator::defaultTerTable()` & di-seed ke `TaxSetting('ter_monthly')`.
> - Method baru `calculateMonthlyTER()` + `terCategory()` + `terRate()` untuk masa Jan–Nov.
> - `PayrollRunController` & `TaxController::recalculate()` mengarahkan masa Jan–Nov ke TER; Desember tetap perhitungan tahunan progresif.
> - Diverifikasi terhadap contoh resmi DJP (TK/0 Rp 10jt → 2% → Rp 200.000 ✓).
>
> Dokumen ini dipertahankan sebagai catatan audit "sebelum perbaikan".

## 1. Ringkasan Eksekutif

> **KESIMPULAN (sebelum perbaikan): ❌ BELUM SESUAI.**
> Perhitungan PPh 21 **bulanan (Januari–November)** pada kode ini **masih menggunakan metode lama** (disetahunkan × 12 lalu tarif progresif), **bukan metode TER (Tarif Efektif Rata-rata)** yang diwajibkan sejak 1 Januari 2024 melalui PP No. 58 Tahun 2023.

| Aspek | Status | Keterangan |
|-------|--------|------------|
| PPh 21 Masa Jan–Nov (TER Bulanan) | ❌ **Tidak sesuai** | Memakai metode lama (annualisasi × 12) |
| PPh 21 Masa Desember (perhitungan ulang tahunan) | ✅ Sesuai | Sudah pakai penghasilan riil setahun + tarif progresif |
| TER Harian (pegawai tidak tetap harian) | ❌ **Tidak ada** | Belum diimplementasikan |
| Tarif progresif Pasal 17 (UU HPP) | ✅ Sesuai | 5 lapisan: 5/15/25/30/35% sudah benar |
| Nilai PTKP 2024 | ✅ Sesuai | 54jt s/d 126jt sudah benar |
| Biaya jabatan (5%, maks 500rb/bln) | ✅ Sesuai | Benar, tetapi dalam skema TER **tidak lagi dipakai** untuk masa Jan–Nov |

**Dampak:** Nilai PPh 21 yang dipotong setiap bulan Jan–Nov kemungkinan **berbeda** dari ketentuan DJP. Walaupun total pajak setahun akhirnya dikoreksi di bulan Desember (sehingga setahun penuh tetap benar), **potongan bulanan tidak sesuai aturan** — ini berisiko temuan saat pemeriksaan pajak dan menyebabkan ketidaksesuaian dengan slip gaji standar DJP.

---

## 2. Apa itu TER dan Apa yang Berubah di 2024

Sejak **1 Januari 2024** (PP 58/2023), cara memotong PPh 21 setiap masa pajak berubah total:

- **Masa Januari–November:** PPh 21 = **Penghasilan Bruto Bulanan × Tarif Efektif (TER)**.
  Tidak lagi menghitung biaya jabatan, tidak disetahunkan, tidak dikurangi PTKP setiap bulan. Cukup kalikan bruto sebulan dengan satu angka persentase TER yang dipilih sesuai kategori PTKP dan lapisan penghasilan.
- **Masa Desember (atau masa pajak terakhir):** Baru dilakukan **perhitungan ulang setahun penuh** dengan metode lama (bruto setahun − biaya jabatan − PTKP → tarif progresif Pasal 17), dikurangi PPh 21 yang sudah dipotong Jan–Nov.

### Tiga Kategori TER Bulanan (berdasarkan PTKP)

| Kategori | Status PTKP | PTKP Setahun |
|----------|-------------|--------------|
| **TER A** | TK/0, TK/1, K/0 | Rp 54.000.000 & Rp 58.500.000 |
| **TER B** | TK/2, TK/3, K/1, K/2 | Rp 63.000.000 & Rp 67.500.000 |
| **TER C** | K/3 | Rp 72.000.000 |

> Catatan: Status K/I/0–K/I/3 (suami-istri penghasilan digabung) dipetakan sesuai PTKP gabungannya.

Lampiran PP 58/2023 memuat **127 baris tarif**: 44 baris TER A, 40 baris TER B, 41 baris TER C, dan 2 baris TER Harian.

### Contoh potongan tabel TER A (ilustratif)

| Penghasilan bruto bulanan | Tarif Efektif |
|---------------------------|---------------|
| s/d Rp 5.400.000 | 0% |
| > Rp 5.400.000 – 5.650.000 | 0,25% |
| > Rp 5.650.000 – 5.950.000 | 0,5% |
| … (bertingkat) … | … |
| > Rp 1.400.000.000 | 34% |

- TER A: 0% s/d penghasilan Rp 5,4 jt; maksimum 34% di atas Rp 1,4 M.
- TER B: 0% s/d penghasilan Rp 6,2 jt; maksimum 34%.
- TER C: 0% s/d penghasilan Rp 6,6 jt; maksimum 34%.

> ⚠️ **Tabel lengkap 127 baris wajib diambil dari Lampiran resmi PP No. 58 Tahun 2023.** Tabel di atas hanya kutipan untuk ilustrasi, jangan dijadikan sumber implementasi.

---

## 3. Bukti dari Kode

### 3.1 Metode bulanan saat ini — `Pph21Calculator::calculateMonthly()`

Berkas: [app/Services/Pph21Calculator.php](../app/Services/Pph21Calculator.php#L42-L97)

```php
// Biaya jabatan (5%, max 500K/bln)
$biayaJabatan = min($brutoMonthly * $biayaJabatanPct, $biayaJabatanMax);

// Netto bulanan
$nettoMonthly = $brutoMonthly - $biayaJabatan - $bpjsEmployee;

// Annualize
$nettoAnnual = $nettoMonthly * 12;              // ← metode LAMA

// PKP (Penghasilan Kena Pajak)
$pkp = max($nettoAnnual - $ptkpAnnual, 0);

// Hitung pajak tahunan berdasarkan tarif progresif
$taxAnnual = $this->calculateProgressiveTax($pkp);

// PPh 21 bulanan
$taxMonthly = round($taxAnnual / 12, 0);        // ← dibagi 12
```

**Analisis:** Ini persis metode **pra-2024**: netto disetahunkan (`× 12`), dikurangi PTKP, dikenai tarif progresif, lalu dibagi 12. **Tidak ada** pemanggilan tabel TER, **tidak ada** pemilihan kategori A/B/C, dan **tidak ada** field `ter_rate` di mana pun. Seharusnya untuk masa Jan–Nov cukup: `PPh21 = bruto_bulanan × tarif_TER`.

### 3.2 Tidak ada tabel/seeder TER

Berkas: [database/seeders/TaxBpjsSeeder.php](../database/seeders/TaxBpjsSeeder.php) dan [TaxSetting](../app/Models/TaxSetting.php)

`TaxSetting` hanya menyimpan key: `pph21_brackets`, `ptkp_values`, `biaya_jabatan`. **Tidak ada** key seperti `ter_monthly_a`, `ter_monthly_b`, `ter_monthly_c`, atau `ter_daily`. Artinya tabel TER memang belum pernah dimasukkan ke sistem.

### 3.3 Yang sudah benar — perhitungan Desember

Berkas: [app/Services/Pph21Calculator.php](../app/Services/Pph21Calculator.php#L176-L229)

`calculateDecember()` sudah benar secara konsep PMK-168: memakai **bruto riil setahun** (bukan × 12), kurangi biaya jabatan setahun + BPJS setahun + PTKP, kenakan tarif progresif, lalu kurangi pajak Jan–Nov. **Ini sesuai aturan.**

> Namun ada ketergantungan penting: hasil akhir Desember baru "benar setahun penuh" jika potongan Jan–Nov-nya juga dihitung dengan metode resmi (TER). Karena Jan–Nov masih metode lama, koreksi Desember masih akan menyamakan total setahun, **tetapi distribusi potongan per bulan tetap menyimpang dari ketentuan.**

### 3.4 Tarif progresif & PTKP — sudah benar

- Tarif progresif Pasal 17 UU HPP — [Pph21Calculator.php:18-24](../app/Services/Pph21Calculator.php#L18-L24): lapisan 5%/15%/25%/30%/35% dengan batas 60jt/250jt/500jt/5M ✅
- Nilai PTKP 2024 ✅ (TK/0 = 54jt s/d K/3 = 72jt, K/I = s/d 126jt)

---

## 4. Selisih Perhitungan (Ilustrasi)

Karyawan **TK/0**, bruto **Rp 10.000.000/bln**, BPJS karyawan ~Rp 220.000.

**Metode kode saat ini (lama):**
```
Biaya jabatan   = 500.000
Netto bulanan   = 10.000.000 − 500.000 − 220.000 = 9.280.000
Netto setahun   = 9.280.000 × 12 = 111.360.000
PKP             = 111.360.000 − 54.000.000 = 57.360.000
Pajak setahun   = 57.360.000 × 5% = 2.868.000
PPh 21 / bulan  = 2.868.000 ÷ 12 ≈ 239.000
```

**Metode TER 2024 (seharusnya, Jan–Nov):**
```
Kategori TER A (TK/0), bruto 10.000.000 → tarif efektif ± 2,25% (lihat lampiran)
PPh 21 / bulan  = 10.000.000 × 2,25% ≈ 225.000
```

Angka tarif TER di atas hanya ilustrasi — yang pasti: **nilainya berbeda** dari hasil metode lama, dan **harus diambil dari tabel resmi**.

---

## 5. Rekomendasi Perbaikan

| Prioritas | Tindakan |
|-----------|----------|
| 🔴 Tinggi | Tambahkan **tabel TER (A/B/C + harian)** dari Lampiran PP 58/2023 ke `TaxSetting`/seeder, lengkap dengan `effective_date = 2024-01-01`. |
| 🔴 Tinggi | Buat method baru, mis. `calculateMonthlyTER(float $bruto, string $ptkpStatus)` yang: (1) memetakan status PTKP → kategori A/B/C, (2) mencari tarif TER sesuai lapisan bruto, (3) `PPh21 = round(bruto × tarif)`. |
| 🔴 Tinggi | Ganti pemanggilan `calculateMonthly()` di [PayrollRunController](../app/Http/Controllers/Admin/PayrollRunController.php#L662-L744) untuk masa **Jan–Nov** agar memakai `calculateMonthlyTER()`. |
| 🟡 Sedang | Pertahankan `calculateDecember()` untuk masa Desember (sudah benar), pastikan input `taxJanToNov` berisi akumulasi PPh 21 hasil TER. |
| 🟡 Sedang | Implementasikan **TER Harian** untuk pegawai tidak tetap (jika ada). |
| 🟢 Rendah | Sesuaikan tampilan simulator & slip gaji agar menampilkan "Tarif Efektif (TER)" untuk masa Jan–Nov, bukan rincian biaya jabatan/annualisasi. |
| 🟢 Rendah | Tambah unit test membandingkan output terhadap contoh perhitungan resmi DJP. |

### Catatan implementasi gross-up
Method `grossUpIteration()` juga perlu disesuaikan: iterasi gross-up untuk masa Jan–Nov harus berbasis tarif TER, bukan tarif progresif annualisasi.

---

## 6. Berkas yang Terlibat

| Berkas | Peran | Perlu diubah? |
|--------|-------|---------------|
| [app/Services/Pph21Calculator.php](../app/Services/Pph21Calculator.php) | Inti perhitungan PPh 21 | ✅ Ya (tambah metode TER) |
| [database/seeders/TaxBpjsSeeder.php](../database/seeders/TaxBpjsSeeder.php) | Seed tarif & PTKP | ✅ Ya (tambah tabel TER) |
| [app/Models/TaxSetting.php](../app/Models/TaxSetting.php) | Penyimpanan setting pajak berversi | ✅ Ya (key TER baru) |
| [app/Http/Controllers/Admin/PayrollRunController.php](../app/Http/Controllers/Admin/PayrollRunController.php) | Orkestrasi payroll | ✅ Ya (routing Jan–Nov ke TER) |
| [app/Http/Controllers/Admin/TaxController.php](../app/Http/Controllers/Admin/TaxController.php) | Simulator & bukti potong | 🟡 Sebagian |
| [resources/views/admin/tax/simulator.blade.php](../resources/views/admin/tax/simulator.blade.php) | UI simulator | 🟡 Sebagian |

---

## 7. Sumber

- [Mengulik PP 58/2023: TER dan Perhitungan PPh yang Lebih Simpel — DJP](https://pajak.go.id/en/node/104043)
- [Pemerintah Rilis Aturan Terkait Tarif Efektif PPh Pasal 21 — Ortax](https://ortax.org/pemerintah-rilis-aturan-terkait-tarif-efektif-pph-pasal-21)
- [Aturan Tarif Efektif PPh 21 dan Contoh Perhitungannya — CATAPA](https://catapa.com/en/blog/aturan-tarif-efektif-pph-21-dan-contoh-perhitungannya)
- [Tarif Efektif Rata-Rata PPh 21: Skema TER 2024 — Pro-Int](https://pro-int.co.id/2025/05/05/tarif-efektif-rata-rata/)
- Peraturan Pemerintah No. 58 Tahun 2023 (Lampiran tabel TER) & PMK No. 168/PMK.03/2023

> Untuk implementasi, **wajib** mengacu pada Lampiran resmi PP No. 58 Tahun 2023 (127 baris tarif), bukan kutipan dalam dokumen ini.

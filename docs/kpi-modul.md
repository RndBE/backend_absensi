# Modul KPI — Dokumentasi Implementasi

Dokumen ini mencatat **apa yang sudah dibangun** dan **keputusan yang diambil** saat menerapkan
`kpi-framework.md` ke aplikasi. Kerangka itu tetap acuan normatif; berkas ini menjelaskan wujud
nyatanya di kode dan di mana implementasinya sengaja menyimpang.

Terakhir diperbarui: 14 Agustus 2026.

---

## 1. Ringkasan

| Hal | Angka |
|---|---|
| Migration | 22 (`2026_08_10_000001` … `2026_08_13_000022`) |
| Model | 24 (`app/Models/Kpi*.php`) |
| Controller admin | 13 (`app/Http/Controllers/Admin/Kpi*.php`) + 1 publik (`KpiWorkChainReviewController`) |
| Kelas pendukung | 10 (`app/Support/Kpi*.php`) |
| View | 25 — 23 di `admin/kpi/`, 2 di `review/` |
| Route admin | 55 + 4 publik bertoken |
| Seeder | 2 (`KpiFrameworkSeeder`, `KpiOrgWiringSeeder`) |
| Test | 56 di 8 berkas (`tests/Feature/Kpi*Test.php`) |
| Izin akses | 12 kunci `kpi.*` di `config/admin_permissions.php` |
| Menu sidebar | 14 entri |

Isi basis data setelah kedua seeder jalan (perusahaan tunggal, PT. ARTA TEKNOLOGI COMUNINDO):

| Tabel | Isi |
|---|---|
| `kpi_levels` | 4 (L1 tidak dinilai; L2/L3/L4 dinilai) |
| `kpi_indicators` | 67 — L2 24, L3 24, L4 19 |
| `kpi_cross_items` | 14 — Lapis A 8 butir, Lapis B 6 butir |
| `kpi_division_relations` | 20 (5 divisi saling menilai, dua arah) |
| `kpi_work_relations` | 77 pasangan serah terima dari 13 rantai kerja, semua `source = seeder` |
| `kpi_cross_assessors` | 18 orang |
| `kpi_periods` | 1 (periode uji coba, status **open**, belum ada pengisian) |
| Karyawan bertanda lintas fungsi | 10 |
| Karyawan dikecualikan | 1 |

---

## 2. Cara menjalankan

### 2.1 Pasang skema dan data

```bash
php artisan migrate
```

```bash
php artisan db:seed --class=KpiFrameworkSeeder --force
```

```bash
php artisan db:seed --class=KpiOrgWiringSeeder --force
```

Urutan wajib: `KpiOrgWiringSeeder` menulis penilai silang yang menunjuk ke periode, dan periode
butuh master (level, indikator, butir silang) sudah ada. `DatabaseSeeder` sudah memanggil keduanya
dalam urutan benar ([DatabaseSeeder.php:537](database/seeders/DatabaseSeeder.php:537)).

Keduanya **idempoten** — aman dijalankan ulang. Nama karyawan yang tidak ditemukan dilewati tanpa
menggagalkan seeder, supaya karyawan resign atau berganti nama tidak membuat `db:seed` mati.

> ⚠️ **Jangan jalankan `KpiOrgWiringSeeder` di tengah periode yang sudah dibuka.** Penanda
> `is_cross_functional` dibaca langsung dan tidak ikut dibekukan ke snapshot periode, jadi
> mengubahnya setelah pengisian jalan akan menggeser siapa yang dinilai personal beserta campuran
> A/B-nya — persis yang dilarang Bab 7.2. Lihat [§8 Hal terbuka](#8-hal-terbuka).

### 2.2 Siklus satu periode

| Langkah | Halaman | Route |
|---|---|---|
| 1. Buat / atur periode | Periode Penilaian | `admin.kpi-periods.index` |
| 2. Siapkan baris penilaian | tombol **Generate** di halaman periode | `admin.kpi-periods.generate` |
| 3. Buka periode (bobot dibekukan) | tombol **Buka** | `admin.kpi-periods.open` |
| 4. Penilai silang mengisi Lapis A & B | Penilaian Silang | `admin.kpi-cross.index` |
| 5. Atasan mengisi penilaian | Isi Penilaian | `admin.kpi-assessments.index` |
| 6. HRD memproses anti-penyalahgunaan | Pemrosesan HRD | `admin.kpi-processing.run` |
| 7. Kalibrasi skor | Kalibrasi | `admin.kpi-calibration.index` |
| 8. Sanggahan & rencana perbaikan | Sanggahan / Rencana Perbaikan | `admin.kpi-appeals.*`, `admin.kpi-improvement-plans.*` |
| 9. Lihat hasil | Hasil KPI, Tren Antar Periode | `admin.kpi-results.*` |

**Generate** melewati karyawan yang belum siap dan melaporkan alasannya per orang, dari
`KpiAssessorMap::blockingReason()`:

- `Dikecualikan dari penilaian KPI.` — permanen, bukan masalah yang perlu dibereskan
- `Level KPI belum diisi.`
- `Level L1 tidak masuk penilaian.`
- `Atasan langsung (manager) belum diisi.`
- `Atasan langsung menunjuk ke dirinya sendiri.`

### 2.3 Menjalankan aplikasi di satu jaringan

Ikat server ke IP mesin, bukan localhost — perangkat lain tidak bisa menjangkau `127.0.0.1`
milik komputermu:

```bash
php artisan serve --host=IP_MESIN_INI --port=8080
```

`APP_URL` dan `VITE_HMR_HOST` di `.env` perlu diarahkan ke IP yang sama. `VITE_HMR_HOST` hanya berlaku
saat `npm run dev`; tanpa itu `vite.config.js` memakai `localhost` dan browser di perangkat lain
akan mencari localhost-nya sendiri lalu gagal memuat aset.

### 2.4 Vite

Proyek memakai Vite 7 + `laravel-vite-plugin` 2, tetapi **tidak butuh dev server** untuk dipakai:
`@vite` membaca `public/hot` kalau ada, kalau tidak jatuh ke `public/build/manifest.json`. Selama
`public/hot` tidak ada, aset dilayani dari hasil build.

> ⚠️ **Wajib build ulang setelah mengubah CSS/JS atau menambah kelas Tailwind baru.** Tailwind JIT
> hanya memancarkan kelas yang terlihat saat build. Pernah kejadian: halaman graf memakai
> `touch-none`, `text-slate-400`, `border-slate-800`, dan `268px` yang belum ada di build lama,
> sehingga tata letaknya rusak tanpa error apa pun.

```bash
php artisan view:cache && npm run build
```

> ⚠️ **`view:cache` bukan hiasan.** [tailwind.config.js:5](tailwind.config.js:5) memasukkan
> `./storage/framework/views/*.php` — cache Blade terkompilasi — sebagai sumber kelas. Akibatnya
> ukuran CSS bergantung pada view mana yang kebetulan sudah pernah dibuka: `php artisan view:clear`
> lalu `npm run build` menghasilkan CSS **10 kB lebih kecil**, kehilangan kelas dari setiap view
> yang belum pernah dirender. `view:cache` mengompilasi seluruh 211 view lebih dulu sehingga
> hasilnya tidak lagi bergantung pada kebetulan.

`public/build` masuk `.gitignore`, jadi build harus dijalankan di tiap mesin.

---

## 3. Peta berkas

### 3.1 Migration

| Berkas | Isi |
|---|---|
| `000001_create_kpi_levels_table` | L1–L4 + bobot per perspektif |
| `000002_add_kpi_flags_to_departments_table` | `is_division`, `is_shared_service`, `kpi_code` |
| `000003_add_kpi_fields_to_employees_table` | `kpi_level_id` (di-backfill dari `job_level`), `is_cross_functional` |
| `000004`–`000005` | indikator + rubrik |
| `000006`–`000008` | periode + snapshot level & indikator |
| `000009`–`000011` | assessment, skor butir, hasil akhir |
| `000012`–`000013` | master & pengiriman penilaian silang |
| `000014_create_kpi_followup_tables` | sanggahan, rencana perbaikan, koreksi kemurahan hati |
| `000015_add_kpi_excluded_to_employees_table` | `is_kpi_excluded` |
| `000016_create_kpi_work_relations_table` | peta rantai kerja antar orang |
| `2026_08_12_000017_add_source_to_kpi_work_relations_table` | `source` — pemilik baris: `seeder` atau `manual` |
| `2026_08_12_000018_drop_is_active_from_kpi_work_relations_table` | mencabut `is_active` — pasangan dihapus, tidak ditandai nonaktif |
| `2026_08_12_000019_add_nature_to_kpi_work_relations_table` | `nature` — salah rancang, digantikan `000020` |
| `2026_08_12_000020_replace_nature_with_oversight_side_...` | `oversight_side` — juga salah rancang, digantikan `000021` |
| `2026_08_12_000021_drop_oversight_side_...` | pengawasan tidak disimpan — diturunkan dari `manager_id` |
| `2026_08_13_000022_create_kpi_work_chain_review_tables` | tautan tinjauan Manajer + catatan perubahan |

### 3.2 Kelas pendukung (`app/Support/`)

| Kelas | Tanggung jawab |
|---|---|
| `KpiAssessorMap` | siapa menilai siapa + bobot; alasan penghalang |
| `KpiPeriodSnapshot` | membekukan bobot & indikator saat periode dibuka; deteksi bobot tidak 100% |
| `KpiScoreCalculator` | skor akhir per karyawan dan per periode |
| `KpiCrossScoreCalculator` | Lapis A, Lapis B, campuran, pengisian indikator CO |
| `KpiCrossTargets` | **satu-satunya** sumber aturan sasaran Lapis B |
| `KpiCrossAbuseDetector` | straight-lining, kolusi, retaliasi, kemurahan hati |
| `KpiFollowUp` | ambang rencana perbaikan dan PIP |
| `KpiAttendanceScore` | tangga skor dari data absensi |
| `KpiWorkChainOverseers` | atasan yang dianggap mengetahui sebuah rantai kerja |
| `KpiWorkChainReviewLinks` | menerbitkan/memverifikasi tautan tinjauan, mencatat perubahan |

---

## 4. Aturan penilaian

### 4.1 Peta penilai (Bab 2.1)

| Dinilai | Penilai utama | Bobot | Penilai pendukung | Bobot |
|---|---|---|---|---|
| L4 | atasan langsung (L3) | 70% | atasan dua tingkat, **harus L2** | 30% |
| L3 | atasan langsung | 100% | — | — |
| L2 | atasan langsung | 100% | — | — |

"Atasan langsung" dibaca dari `employees.manager_id`, **bukan** `approver_id` — `approver_id` milik
alur persetujuan dokumen (`App\Models\ApprovalRule`) dan boleh berbeda dari garis komando.

**Dua penyimpangan yang disengaja dari Bab 2.1** ([KpiAssessorMap.php:20](app/Support/KpiAssessorMap.php:20)):

1. **Direksi tidak menilai L3.** Bab 2.1 memberi Direksi porsi pendukung 30% untuk L3. Seluruh
   Manajer melapor ke satu Direktur, jadi porsi itu membuat satu orang memegang belasan formulir
   sekaligus — beban yang Bab 9.1 sendiri peringatkan akan membuat penilai mengisi sekadar selesai.
2. **Level penilai pendukung diperiksa, bukan hanya posisinya di rantai.** Tanpa itu, staf L4 yang
   atasan langsungnya seorang Manajer menarik Direksi sebagai pendukung hanya karena Direksi
   kebetulan berada dua tingkat di atasnya.

Efek pada beban Direktur: **27 → 5 assessment**.

**Harga yang dibayar, dan sudah disetujui:** seluruh L3 dan sebagian L4 sekarang dinilai satu
orang, jadi peredam bias penilai kedua hilang. **Kalibrasi Bab 9.2 menjadi satu-satunya penyaring
standar antar penilai untuk mereka — sesi itu tidak boleh dilewati.**

Kalau atasan dua tingkat tidak ada, atau ada tetapi levelnya bukan L2, penilai utama mengambil
seluruh 100%. Bobot 30% tidak pernah dibiarkan hilang: kalau hilang, nilai akhir orang itu hanya
terbentuk dari 70% bobot dan tidak sebanding dengan rekan selevelnya.

### 4.2 Bobot perspektif per level

| Level | Excellence (EX) | Contribution (CO) | Leadership (LD) |
|---|---|---|---|
| L2 Manajer | 40% | 15% | 45% |
| L3 Leader/SPV | 50% | 20% | 30% |
| L4 Staff | 70% | 25% | 5% |

Bobot butir di dalam setiap kategori berjumlah 100 untuk semua level.

### 4.3 Penilaian silang dua lapis

|  | Lapis A | Lapis B |
|---|---|---|
| Yang dinilai | **divisi** sebagai unit | **orang** |
| Butir | 8 (XA-01…08) | 6 (XB-01…06) |
| Pengisi | semua penilai silang | hanya yang `can_assess_individual` |

**Ini pembeda paling mudah tertukar.** Lapis A menilai divisi, jadi dua penilai Lapis A **tidak
pernah** saling menilai. Satu-satunya relasi orang-ke-orang di penilaian silang adalah Lapis B.
Aturan sasarannya tinggal satu salinan di `App\Support\KpiCrossTargets` — dipakai bersama halaman
pengisian dan graf relasi. **Jangan disalin lagi ke tempat lain.**

Sasaran Lapis B = karyawan aktif, tidak dikecualikan, di divisi mitra, yang **L2/L3 atau bertanda
`is_cross_functional`**.

### 4.4 Campuran A/B per level (Bab 7.9 Langkah 3)

| Level | Lapis A | Lapis B |
|---|---|---|
| L2 | 50% | 50% |
| L3 | 40% | 60% |
| L4 lintas fungsi | 50% | 50% |
| L4 biasa | 100% | 0% |

### 4.5 Kuorum dan nilai bawaan

Kuorum Bab 7.7: **minimal 3 penilai dari minimal 2 divisi berbeda**. Gagal kuorum → skor bawaan
**3,0**. Ketiadaan data bukan berarti kinerja buruk.

Kuorum dihitung pada divisi yang **dinilai**, bukan pada divisi penilai.

### 4.6 Bobot skor silang terhadap nilai akhir

Skor kolaborasi masuk lewat indikator kategori CO yang bertanda `auto_source = cross_assessment`:

| Level | Bobot CO yang otomatis | × bobot kategori CO | = porsi nilai akhir |
|---|---|---|---|
| L2 | 70 dari 100 | 15% | **10,5%** |
| L3 | 44 dari 100 | 20% | **8,8%** |
| L4 | 20 dari 100 | 25% | **5,0%** |

Artinya seluruh mesin penilaian silang — dua lapis, 14 butir, deteksi penyalahgunaan — hanya
menentukan **5%** nilai akhir seorang staf L4. Lihat [§8](#8-hal-terbuka).

### 4.7 Deteksi penyalahgunaan

| Mekanisme | Ambang | Tindakan |
|---|---|---|
| Straight-lining | jawaban seragam | baris diinvalidasi otomatis |
| Retaliasi | skor ≤ 2,5 | ditandai untuk ditinjau |
| Kolusi | mutual ≥ 4,75 dengan jarak ≥ 1,0 dari rata-rata | dipangkas |
| Kemurahan hati | — | koreksi diterapkan saat hitung, angka mentah tetap utuh |

Koreksi kemurahan hati **tidak** ditulis ke baris skor supaya angka mentah penilai tetap utuh dan
koreksinya bisa dibatalkan.

### 4.8 Ambang tindak lanjut

| Ambang | Nilai |
|---|---|
| Rencana perbaikan individu | < 3,0 |
| PIP | < 2,0 |
| Rencana perbaikan divisi | < 3,0 |

---

## 5. Keputusan struktur organisasi

Semuanya di [`KpiOrgWiringSeeder`](database/seeders/KpiOrgWiringSeeder.php) — dipisah dari
`KpiFrameworkSeeder` karena sifatnya beda: `KpiFrameworkSeeder` berisi master yang berlaku untuk
perusahaan mana pun, berkas ini berisi keputusan yang tidak bisa diturunkan dari data mana pun dan
harus ditetapkan manusia.

### 5.1 Divisi

| Departemen | Kode | Layanan umum |
|---|---|---|
| FAT & SUPPLY CHAIN | FAT | ✔ |
| HRD & CORPORATE SERVICE | HRD | ✔ |
| SOFTWARE DIVISION | SOFT | — |
| HARDWARE DIVISION | HARD | — |
| MARKETING & SALES | MKT | — |

Dicopot dari daftar divisi: **BOARD OF DIRECTORS** dan **SUPERADMIN**. Bab 7.3 melarang menilai
divisi sendiri dan Bab 2.1 tidak menilai L1 — membiarkannya bertanda divisi membuat halaman matriks
selalu melaporkan masalah "mitra kurang dari 3" yang tidak akan pernah bisa dibereskan.

### 5.2 Matriks relasi: semua-ke-semua

5 divisi saling menilai seluruhnya → 4 mitra per divisi, masih di antara batas Bab 7.3 (minimal 3,
maksimal 6). **Jangan dipangkas:** dengan hanya lima divisi, memangkasnya lagi akan menjatuhkan
sebagian divisi di bawah kuorum tiga penilai dari dua divisi berbeda.

Sembilan pasangan berasal dari rantai kerja nyata; Marketing↔HRD masuk lewat aturan layanan umum
Bab 7.3.

### 5.3 Garis penilai yang diperbaiki

13 staf L4 dipindah dari Manajer ke Leader L3 divisinya. **`approver_id` sengaja tidak disentuh.**

**Pengecualian yang disengaja** — tetap menunjuk Manajernya:

- **RnD** (Hardware) — Prastowo, Ilham
- **Purchasing** dan **Project Operation Admin** (FAT) — Lina, Zainni

Leader L3 di kedua divisi itu (Production dan Tax Officer) bukan atasan fungsional mereka;
memaksakan pola Bab 2.1 di sana justru menghasilkan penilai yang tidak tahu pekerjaan yang dinilai.
Accounting & Finance tidak ikut dikecualikan — atasan fungsionalnya memang Leader L3 di divisinya
(Maritza → Dewi Pusporini).

### 5.4 Level lewat override, bukan `job_level`

**Haryanto** (security alih daya) diberi `kpi_level_id` = L4 lewat `$kpiLevelOverrides`, bukan
dengan mengisi `job_level`-nya. `job_level` adalah dasar `ApprovalRule::min_approver_level`;
mengubahnya akan menggeser alur persetujuan izin dan lembur tanpa diminta.

Level yang sudah diisi admin tidak ditimpa — seeder hanya menambal yang kosong.

### 5.5 Sepuluh staf L4 lintas fungsi

Daftar ini **otoritatif**: nama yang tidak tercantum akan di-set `false`. Kalau hanya menambah,
penanda orang yang dicabut akan menempel selamanya di basis data dan berbeda dari berkas ini —
persoalan serius karena Bab 7.2 menuntut penandaan bisa diaudit sebagai keadaan di awal periode.

| Orang | Peran penghubung |
|---|---|
| Zainni Novena Santi | invoice, SPK, harga modal CRM — tiga divisi |
| Shandy Bagus Ferdiansyah | pegang inventory, CRM, dan HRIS |
| Maritza Isyaura Putri Rizma | faktur, pembayaran klien, harga modal |
| Afif Faishahuda | pembayaran, target proyek, CRM |
| Lina Widiastuti | bahan produksi dan barang riset ke Hardware |
| Anisa Febriyanti | serah terima pekerjaan, inventory |
| Prastowo Dian Kristiyanto | riset dengan Software, kustomisasi item, laporan riset |
| Ilham Yoga Pratama | sama seperti Prastowo |
| Rasyid Priyo Nugroho | target penyelesaian proyek dengan Marketing |
| Tata Azkia Azzahra | desain untuk RnD — relasi paling tipis di daftar ini |

Dikecualikan permanen: **Apple Demo Account** (akun demo, bukan orang).

### 5.6 Penilai silang (18 orang)

Dipilih dari rantai kerja yang benar-benar berjalan, bukan dari jabatan — Bab 7.4: "penilai dipilih
berdasarkan intensitas interaksi kerja, bukan jabatan semata". Kolom kedua = `can_assess_individual`
(boleh mengisi Lapis B).

| Divisi | Penilai | Lapis B |
|---|---|---|
| FAT & Supply Chain | Wahyu, Dewi Pusporini | ✔ |
| | Zainni, Maritza, Lina | — |
| Hardware | Subarkah, Rhomadoni | ✔ |
| | Prastowo, Ilham, Anisa | — |
| Software | Nofiyanto, Fadel | ✔ |
| | Shandy, Tata | — |
| Marketing & Sales | Dewi Setiawati, Zaeni | ✔ |
| | Afif | — |
| HRD & Corporate Service | Avissa | ✔ |

**Dua konsekuensi yang diterima dengan sadar:**

**Rasyid keluar** — kuota Bab 7.4 lima penilai per divisi sudah penuh setelah kedua staf RnD masuk
atas keputusan manajemen. Relasi yang dia bawa (target penyelesaian proyek dengan Marketing) sudah
diwakili Rhomadoni: Leader Production, penilai A+B, atasan langsungnya. Anisa dipertahankan karena
serah terima pekerjaan fungsi yang berbeda, dan justru dialah yang punya dasar paling kuat menilai
butir XA-03 soal kelengkapan dan akurasi serah terima.

**HRD hanya satu penilai** — di bawah anjuran tiga Bab 7.4, dan itu memang keadaannya. Dari lima
orang HRD, hanya Avissa yang punya kontak lintas divisi; empat sisanya (supporting staff dan
security) bekerja ke dalam. Mengisi kuota dengan mereka justru merusak datanya: penilai yang tidak
punya dasar cenderung menjawab seragam, dan `KpiCrossAbuseDetector` membuang kuesioner seragam
sebagai straight-lining — barisnya tetap tidak terhitung, sementara divisi lain ikut menanggung
kesan bahwa HRD sudah menilai. Kuorum tidak terganggu: HRD sebagai **sasaran** tetap dinilai
belasan penilai dari empat divisi mitra.

Konsekuensi lain: Avissa adalah satu-satunya permukaan lintas divisi HRD dan memikul ±140 kolom
formulir.

**Penjaga kuota:** `assertAssessorQuota()` melempar `RuntimeException` kalau daftar melewati
`KpiCrossAssessor::MAX_PER_DIVISION`. `KpiCrossMatrixController::storeAssessor` menolak penilai
ke-6, tetapi seeder menulis lewat `updateOrCreate` sehingga tidak melewati penjagaan itu — tanpa
pemeriksaan, menambah satu nama bisa menghasilkan keadaan yang tidak mungkin dibuat lewat antarmuka.

Daftar penilai juga **otoritatif**, tetapi pencabutan **dilewati untuk penilai yang sudah pernah
mengirim kuesioner**. Baris di `kpi_cross_layer_a`/`layer_b` menunjuk ke `employees`, bukan ke baris
penilai, jadi menghapus penilainya tidak ikut menghapus jawaban — yang tersisa justru jawaban dari
orang yang tidak lagi tercatat berwenang, dan itu lebih sulit dijelaskan saat audit.

### 5.7 Beban penilai sekarang

Keadaan setelah kedua seeder jalan — **27 karyawan dinilai, dibagi ke 10 penilai**:

| Beban | Penilai | Level |
|---|---|---|
| 7 | Muhammad Subarkah | L2 |
| 5 | Sofyan Ariyanto | L1 (Direktur) |
| 4 | Nofiyanto | L2 |
| 4 | Wahyu Nurul Haryanto | L2 |
| 4 | Avissa Nova Fauzistika | L3 |
| 4 | Rhomadoni | L3 |
| 3 | Dewi Setiawati | L2 |
| 3 | Fadel Muhammad Irsyad | L3 |
| 2 | Akhmad Zaeni Mustofa | L3 |
| 1 | Dewi Pusporini | L3 |

Tidak ada karyawan tersangkut karena penyetelan yang belum beres. Yang tidak dinilai: 3 orang L1
(level itu memang tidak masuk penilaian) dan 1 akun demo yang dikecualikan.

### 5.8 Periode uji coba

| Field | Nilai |
|---|---|
| Nama | Uji Coba Semester I 2026 |
| Rentang dinilai | 2026-01-01 … 2026-06-30 |
| Pengisian silang | 2026-08-11 … 2026-08-24 |
| Pengisian atasan | 2026-08-25 … 2026-09-05 |
| Status saat dibuat | draft, `is_trial = true` |
| Status sekarang | **open** — sudah dibuka admin, bobot terbekukan, pengisian masih 0 |

Rentang enam bulan sesuai Bab 7.7 — yang dinilai perilaku sepanjang periode, bukan kejadian terbaru
saja. Dibuat berstatus draft supaya admin tetap yang memutuskan kapan bobot dibekukan.

> ⚠️ Karena periode sudah **open**, seeder tidak boleh dijalankan ulang begitu pengisian mulai
> masuk — `is_cross_functional` dibaca langsung dan tidak ikut dibekukan. Selama
> `kpi_cross_layer_a` dan `layer_b` masih kosong, menjalankan ulang masih aman
> ([§8.3](#83-is_cross_functional-tidak-ikut-dibekukan)).

Hanya dibuat kalau perusahaan **belum punya periode sama sekali**. Setelah admin mulai membuat
periode sendiri, seeder tidak menambah baris — daftar periode adalah catatan resmi siklus
penilaian, bukan tempat data contoh.

---

## 6. Peta rantai kerja

Lapisan **terpisah** dari relasi penilaian, disimpan di `kpi_work_relations`
(`App\Models\KpiWorkRelation`). **Tidak dipakai perhitungan apa pun** — murni peta alur kerja untuk
ditinjau.

13 rantai → 77 pasangan, 20 orang. `from` menyerahkan/mengoordinasikan sesuatu kepada `to`;
arahnya dicatat karena berguna saat menelusuri hambatan, meski di graf digambar sebagai satu garis.

Nama **bertanda tebal** adalah Manajer yang masuk atas dasar pengawasan, bukan serah terima langsung
— lihat [§6.3](#63-manajer-masuk-atas-dasar-pengawasan). Beberapa rantai punya lebih dari satu baris
karena sisinya berbeda — lihat [§6.4](#64-satu-label-boleh-muncul-lebih-dari-sekali).

| Label | Dari | Ke | Pasangan |
|---|---|---|---|
| Bahan produksi | Lina | Rhomadoni, Anisa, **Subarkah** | 3 |
| Barang riset & proyek | Lina | Prastowo, Ilham, **Subarkah** | 3 |
| Pembayaran klien & faktur | Wahyu, Dewi P., Maritza | Dewi S., Zaeni, Afif | 9 |
| Invoice | Zainni, Dewi P., Maritza | Dewi S., Zaeni, Afif | 9 |
| SPK | Zainni | Dewi S., Zaeni, Afif, Rhomadoni, Anisa, **Subarkah**, Fadel, **Nofiyanto** | 8 |
| Harga modal di CRM | Zainni, Maritza | Shandy | 2 |
| Harga modal di CRM | Zainni | Dewi S., Zaeni, Afif | 3 |
| Website inventory | Shandy | Rhomadoni, Rasyid, Anisa, Lina, Maritza, Dewi P., **Wahyu** | 7 |
| Website inventory | **Nofiyanto** | Rhomadoni, Rasyid, Anisa, Lina, Maritza | 5 |
| Website inventory | Lina | Rhomadoni, Anisa | 2 |
| Website inventory | Zainni | Shandy | 1 |
| CRM penawaran | Shandy | Dewi S., Zaeni, Afif, Zainni | 4 |
| CRM penawaran | **Nofiyanto** | Dewi S., Zaeni, Afif | 3 |
| HRIS absensi & payroll | Shandy | Avissa, **Wahyu**, Maritza, Dewi P. | 4 |
| HRIS absensi & payroll | **Nofiyanto** | Avissa, **Wahyu**, Maritza | 3 |
| HRIS absensi & payroll | Avissa | Dewi P. | 1 |
| Riset | Prastowo, Ilham | Fadel, Shandy, Tata, Syarif, **Nofiyanto** | 10 |
| Riset | **Nofiyanto** | **Subarkah** | 1 |
| Laporan hasil riset | Prastowo, Ilham, Anisa | Avissa, Wahyu | 6 |
| Target penyelesaian proyek | Dewi S., Zaeni, Afif | Rhomadoni, **Subarkah** | 6 |
| Kustomisasi item | Dewi S., Zaeni, Afif | Prastowo, Ilham | 6 |

Enam pasangan **dihapus** 12 Agustus 2026, seluruhnya bermuara pada satu prinsip: **koordinasi
lintas divisi berhenti di Leader dan Manajer, tidak turun ke pelaksana.**

| Rantai | Pasangan dicabut |
|---|---|
| Bahan produksi | Lina → Rasyid, Endarto, Alvian |
| Target penyelesaian proyek | Dewi S., Zaeni, Afif → Rasyid |

Akibatnya kedua welder tidak punya relasi lintas divisi sama sekali, dan Rasyid tinggal 2 pasangan —
keduanya di website inventory. Riwayat pencabutannya ada di komentar `$workChains` lewat git, bukan
di basis data.

### 6.1 Halaman Rantai Kerja — admin bisa menyunting sendiri

Menu **Rantai Kerja** (`admin.kpi-work-chains.index`), izin `kpi.cross.master.manage`. Sisi kiri
daftar rantai berurut dari yang terpadat, sisi kanan isi tiap rantai plus form penambahan.
Karyawan diambil dari tabel `employees` (aktif, tidak dikecualikan), dikelompokkan per departemen.

| Aksi | Route |
|---|---|
| Rantai baru | `kpi-work-chains.store` |
| Tambah pasangan ke rantai | `kpi-work-chains.add-pairs` |
| Nonaktifkan satu pasangan | `kpi-work-chains.deactivate` |
| Aktifkan kembali | `kpi-work-chains.reactivate` |
| Nonaktifkan seluruh rantai | `kpi-work-chains.deactivate-chain` |

**Kolom `source` wajib ada sebelum halaman ini berguna.** `buildWorkChains()` bersifat otoritatif —
pasangan yang tidak tercantum di `$workChains` dihapus. Tanpa penanda pemilik baris, setiap
`db:seed` akan membuang seluruh rantai buatan admin tanpa pesan apa pun. Sekarang seeder hanya
berkuasa atas baris `source = seeder`; sudah diuji dengan menjalankan seeder penuh sementara ada
baris manual — baris itu bertahan dan 98 baris seeder tetap utuh.

**Pengawasan tidak disimpan — diturunkan dari `manager_id`.** Tiap kartu rantai punya baris
*Diketahui* berisi atasan para pesertanya, dihitung [`KpiWorkChainOverseers`](app/Support/KpiWorkChainOverseers.php)
saat halaman dibuka. Aturannya: telusuri garis atasan tiap peserta ke atas, **berhenti di Manajer
(L2)**, Direksi tidak ditampilkan, dan peserta langsung tidak diulang.

Dua rancangan sebelumnya salah dan masing-masing hidup hanya beberapa jam:

| Migration | Rancangan | Kenapa gagal |
|---|---|---|
| `000019` | `nature` = `direct`/`oversight` | tidak menyebut SIAPA yang mengetahui, jadi lencana selalu menempel di sisi `to` — muncul "Nofiyanto → Anisa (mengetahui)" padahal Anisa pemakai sistemnya |
| `000020` | `oversight_side` = `from`/`to` | sisinya benar, tapi tetap disimpan per pasangan |

Yang menjatuhkan keduanya: **"atasan mengetahui" bukan hubungan antar dua orang.** Konsekuensinya
tiga, dan ketiganya terbukti:

1. **Satu kenyataan jadi banyak baris.** Nofiyanto mengetahui koordinasi inventory timnya — satu
   fakta — tercatat 5 baris, dan 11 baris di tiga rantai. Di graf dia jadi simpul terpadat kedua.
2. **Penandaan tangan selalu bolong.** Tercatat Nofiyanto (Manajer, dua tingkat di atas Shandy)
   tetapi tidak **Fadel** — Leader Software, atasan langsung Shandy. Wahyu di bahan produksi,
   Rhomadoni di laporan hasil riset, dan seluruh pengawas rantai desain untuk RnD juga terlewat.
3. **Melengkapinya dengan tangan tidak terpelihara.** 2–6 penanda per rantai yang harus ditambah
   ulang tiap ada peserta baru.

Semuanya sudah ada di garis `manager_id`, jadi menyimpannya hanya menduplikasi struktur organisasi
dalam bentuk yang bisa basi. Sekarang nol baris disimpan, nol perawatan, dan Leader ikut terbawa.

Dua rantai keluar tanpa pengawas — **Riset** dan **Pembayaran klien & faktur** — karena seluruh
atasan pesertanya sudah jadi peserta langsung; yang tersisa hanya Direksi. Kartunya menampilkan
keterangan itu terang-terangan, bukan dibiarkan kosong.

**Penambahan selalu meminta kedua sisi sekaligus**, tidak menambah orang ke satu sisi saja. Alasannya
sama dengan yang membuat `$workChains` perlu dipecah jadi beberapa baris berlabel sama
([§6.4](#64-satu-label-boleh-muncul-lebih-dari-sekali)): menambah satu nama ke sisi "dari" ikut
melahirkan pasangan ke seluruh sisi "ke". Jumlah pasangan yang akan lahir ditampilkan sebelum
disimpan, dan sekali simpan dibatasi 60 pasangan (`MAX_PAIRS_PER_SUBMIT`).

Pemilih orang dirender **satu kali** ke dalam `<template>` lalu dikloning saat kartu rantai dibuka.
Kalau tiap kartu merender pemilihnya sendiri, halaman memuat 2 × jumlah_karyawan × jumlah_rantai
kotak centang — dengan 30 karyawan dan 14 rantai sudah 900 kotak dan 1 MB HTML, tumbuh lurus seiring
karyawan bertambah. Dengan template: 434 kB dan 60 kotak.

**Pasangan dihapus, tidak ditandai nonaktif** (keputusan manajemen 12 Agustus 2026, migration
`000018`). Penonaktifan dipilih semula agar peta lama bisa direkonstruksi, tetapi alasan itu tidak
berlaku di tabel ini: tidak ada perhitungan KPI yang membacanya, jadi tidak ada angka yang bergantung
pada baris yang hilang, dan riwayat peta versi seeder sudah tersimpan di `$workChains` lewat git —
sumber yang lebih jujur, karena menyimpan **alasan** perubahan di komentarnya, bukan cuma fakta bahwa
dulu pernah ada. Yang tertinggal dari model lama hanyalah baris nonaktif yang tak terlihat di halaman
mana pun dan tak bisa diaktifkan siapa pun.

Menghapus baris `source = seeder` hanya bertahan sampai `db:seed` berikutnya kalau pasangannya masih
tercantum di `$workChains` — di situ berkas seeder yang berkuasa. Pesan balik dan dialog konfirmasi
menyebutkan itu, supaya admin tidak menebak-nebak kenapa pasangannya muncul lagi.

### 6.2 Tautan tinjauan Manajer — tanpa login

Menu tidak ada: halaman ini hanya dijangkau lewat tautan bertoken, satu per Manajer.

```bash
php artisan kpi:review-links                 # terbitkan untuk semua Manajer (L2)
php artisan kpi:review-links --name="NOFIYANTO" --days=14
php artisan kpi:review-links --list          # jejak pemakaian; tautannya TIDAK bisa ditampilkan ulang
php artisan kpi:review-links --revoke-all
```

**Kenapa tanpa login.** Manajer perlu memeriksa peta sekali dua kali lalu selesai. Membuatkan mereka
akun admin berarti memberi jalan masuk permanen ke seluruh HRIS — termasuk payroll — untuk pekerjaan
sepekan.

**Yang menahan risikonya.** Token 64 karakter disimpan sebagai `sha256`, mengikuti
`employee_magic_links`; kebocoran basis data tidak menyerahkan tautan yang bisa dipakai. Tautan bisa
kedaluwarsa dan dicabut, punya `throttle:30,1`, ber-`noindex` dan `no-referrer`, dan setiap pembukaan
dicatat. Berbeda dari magic link portal, tautan ini **bukan sekali pakai** — manajer membukanya
berkali-kali selama masa tinjauan.

| Kewenangan | Tautan tinjauan | Halaman admin |
|---|---|---|
| Lihat seluruh rantai | ✔ | ✔ |
| Hapus satu pasangan | ✔ | ✔ |
| Tambah pasangan | ✔ | ✔ |
| Buat rantai baru | ✔ (sejak 14 Agu 2026) | ✔ |
| Hapus rantai utuh | — | ✔ |

Penghapusan rantai utuh sengaja ditahan: membuat rantai menambah baris yang kelihatan dan bisa
dihapus satu-satu, sedangkan sekali klik hapus-rantai melenyapkan belasan baris — dan tautan tanpa
login bisa diteruskan ke grup obrolan tanpa disadari.

**Catatan perubahan** masuk `kpi_work_chain_edit_logs` beserta pelaku, sumber (`review` / `admin`),
IP, dan peramban. Halaman admin ikut mencatat ke tabel yang sama supaya riwayatnya bisa dibaca
sebagai satu urutan kejadian, bukan terpecah dua tempat. Catatannya **ditampilkan di halaman
tinjauan itu sendiri**: kalau ada yang salah ubah atas nama seorang manajer, dia yang paling cepat
menyadarinya.

Rantai yang menyentuh divisi si peninjau diurutkan paling atas — itu yang paling dia kenal.

### 6.3 Manajer masuk atas dasar pengawasan

Keputusan manajemen 12 Agustus 2026, menjawab temuan bahwa Subarkah dan Nofiyanto tidak muncul di
rantai mana pun sementara Manajer Marketing dan Manajer FAT muncul: **keduanya memang ikut, karena
mengetahui koordinasi yang dijalankan anak buahnya.** Kehadirannya bersifat pengawasan, bukan serah
terima langsung. Wahyu (Manajer FAT) masuk atas dasar yang sama di website inventory dan HRIS — di
HRIS dia turun dari serah terima langsung menjadi pengawasan.

| Manajer | Rantai | Pasangan |
|---|---|---|
| Nofiyanto | SPK, Website inventory, CRM penawaran, HRIS, Riset | 15 |
| Muhammad Subarkah | Bahan produksi, Barang riset, SPK, Riset, Target penyelesaian | 7 |
| Wahyu Nurul Haryanto | Website inventory, HRIS — 2 dari 9 pasangannya | 2 |

Dari 98 pasangan, **19 berdasar pengawasan**. Cakupannya **sengaja dibatasi** pada rantai yang
disebut manajemen. Memasukkan Subarkah dan Nofiyanto ke setiap rantai yang diikuti anak buahnya
adalah keputusan terpisah yang belum diambil: puluhan pasangan tambahan akan membuat keduanya tampak
lebih padat daripada orang yang benar-benar mengerjakan serah terima.

### 6.4 Satu label boleh muncul lebih dari sekali

Pasangan dihasilkan dari perkalian `from` × `to`, jadi satu baris tidak bisa menyatakan "Zainni ke
Marketing" tanpa sekaligus membuat "Maritza ke Marketing" kalau keduanya berada di `from` yang sama.
Rantai yang punya beberapa sisi berbeda karena itu dipecah jadi beberapa baris berlabel sama:
`Harga modal di CRM` 2 baris, `Website inventory` 4, `CRM penawaran` 2, `HRIS absensi & payroll` 3,
`Riset` 2.

`buildWorkChains()` memang menerimanya, dan indeks unik `(from, to, label)` tetap aman selama tidak
ada pasangan yang terulang. **Jangan gabungkan baris berlabel sama "supaya rapi"** — itu akan
memunculkan pasangan yang tidak pernah dikonfirmasi siapa pun.

### 6.5 Dasar setiap pasangan

Dicatat di lembar konfirmasi, bukan di basis data — kolom `label` hanya menyimpan nama rantai.

| Dasar | Jumlah | Arti |
|---|---|---|
| Disebut langsung | 28 | kedua sisi disebut namanya oleh manajemen |
| Turunan divisi | 51 | divisinya yang disebut, orangnya diisi dari anggota divisi itu |
| Pengawasan | 19 | Manajer yang mengetahui, bukan pelaku serah terima |
| Tebakan | 0 | habis setelah rantai Riset dikonfirmasi 12 Agustus 2026 |

Rinciannya per pasangan ada di [docs/kpi-konfirmasi-rantai-kerja.md](kpi-konfirmasi-rantai-kerja.md).

Lebih dari separuh pasangan (51 dari 98) adalah **turunan** dari keterangan tingkat divisi, bukan
disebut satu per satu — "FAT berkoordinasi dengan Marketing soal faktur" sendiri menghasilkan
sembilan pasangan. Perlu ditandai ulang kalau ada yang sebenarnya tidak pernah terjadi; lembar
konfirmasi menandai mana saja yang begitu.

**Yang masih perlu dikonfirmasi** (nomor mengikuti lembar konfirmasi): Q3 laporan riset ke HRD, Q4
Okka tanpa relasi, Q5 sembilan pasangan faktur dan invoice, Q7 empat orang HRD dan security, Q8
Rasyid tinggal di website inventory saja, Q9 kedua welder kini tanpa relasi, Q10 cakupan pengawasan
Manajer.

Peta ini **otoritatif dan menghapus**: pasangan yang tidak lagi tercantum di `$workChains` dibuang
dari basis data. Riwayatnya hidup di git, pada berkas seeder itu sendiri — di sana tercatat pula
alasan setiap pencabutan, yang tidak bisa disimpan oleh sebuah kolom penanda.

---

## 7. Graf Relasi Divisi

Menu baru: **Graf Relasi Divisi** (`admin.kpi-relation-graph.index`), izin
`kpi.cross.master.manage`.

- **Satu simpul = satu karyawan**, bukan satu divisi. Graf lima simpul divisi tidak memberi tahu apa
  pun yang tidak sudah terbaca di tabel Matriks Relasi Kerja. Yang tidak terlihat di tabel justru
  sebaran orangnya: siapa yang menanggung banyak garis penilaian sekaligus, divisi mana yang seluruh
  hubungan luarnya bertumpu pada satu orang, dan siapa yang sama sekali tidak tersambung ke divisi
  lain.
- Warna mengikuti divisi; ukuran simpul mengikuti derajat.
- Simpul berderajat 0 disaring keluar setelah derajat dihitung.
- **Halaman ini hanya membaca.** Penyuntingan mitra tetap di Matriks Relasi Kerja supaya aturan
  Bab 7.3 hidup di satu tempat saja.

Tiga jenis sisi, bisa disaring:

| Jenis | Arti | Gaya |
|---|---|---|
| `komando` | garis penilai atasan–bawahan | garis penuh, pendek dan kaku |
| `silang` | sasaran Lapis B | garis penuh, panjang dan lentur |
| `kerja` | rantai kerja nyata | garis putus-putus |

Tata letak force-directed vanilla JS/SVG, tanpa library luar.

---

## 8. Hal terbuka

### 8.1 Keamanan — belum ditindak, dan sekarang aplikasinya hidup di jaringan

- **`APP_DEBUG=true`** di `.env`. Halaman error akan menampilkan jejak tumpukan, isi variabel
  lingkungan, dan potongan kueri ke siapa pun di jaringan yang memicu error. Untuk instalasi yang
  bisa diakses orang lain, ini perlu `false`.
- **Akun karyawan hasil seeder memakai kata sandi `password`**
  ([KaryawanPtArtaSeeder.php:174](database/seeders/KaryawanPtArtaSeeder.php:174)). Selama aplikasi
  bisa dijangkau satu jaringan, siapa pun yang tahu satu nama karyawan bisa masuk.

### 8.2 Penanda Tata belum diputuskan

Permintaannya bertentangan: "jangan dicabut" dari lintas fungsi, tetapi juga jangan tersambung ke
HRD, Wahyu, dan Marketing.

Kalau dicabut: 7 sisi penilaian silang hilang, skor kolaborasinya jadi 100% nilai divisi Software,
dan sisi rantai kerja ke Prastowo/Ilham **tetap ada** karena lapisan itu terpisah. Satu baris di
seeder.

**Jebakan yang perlu diingat** kalau muncul permintaan serupa "orang ini cukup dinilai satu divisi
saja": kuorum Lapis B menuntut ≥3 penilai dari ≥2 divisi, jadi membatasi seseorang ke satu divisi
membuat Lapis B-nya gagal kuorum permanen dan skornya jatuh ke 100% nilai divisi — hasilnya identik
dengan mencabut penanda, hanya dengan tambahan orang mengisi formulir yang dibuang. Tawarkan
pencabutan penanda, bukan pembatasan divisi.

### 8.3 `is_cross_functional` tidak ikut dibekukan

Dibaca langsung, tidak masuk snapshot periode. Mengubahnya di tengah periode menggeser hasil —
melanggar Bab 7.2. Belum ada penjagaan di kode; untuk sekarang hanya disiplin operasional
([§2.1](#21-pasang-skema-dan-data)).

### 8.4 `fillAutoScores()` masih no-op

Metode itu mencari indikator bertanda `auto_source = attendance`, dan **tidak ada satu pun** di 67
indikator sekarang. Jadi `KpiAttendanceScore` sudah jadi tetapi belum pernah terpakai. Perlu
menandai indikator yang memang bersumber absensi.

### 8.5 Porsi skor silang mungkin terlalu kecil

Seluruh mesin penilaian silang hanya menentukan 5% nilai akhir staf L4
([§4.6](#46-bobot-skor-silang-terhadap-nilai-akhir)). Perlu diputuskan apakah angka itu memang yang
dimaksud.

### 8.6 Kecil

- Entri `hris-local` dengan `127.0.0.1` masih ada di `.claude/launch.json`; bisa dicopot kalau tidak
  dipakai.

---

## 9. Test

56 test di 8 berkas. Semua memakai sqlite in-memory dengan skema yang dibangun tangan di
`tests/Concerns/CreatesKpiSchema.php`.

| Berkas | Test |
|---|---|
| `KpiAssessorMapTest` | 11 |
| `KpiCrossScoreCalculatorTest` | 9 |
| `KpiCrossAbuseDetectorTest` | 8 |
| `KpiAssessmentSubmitTest` | 8 |
| `KpiPeriodSnapshotTest` | 7 |
| `KpiFollowUpTest` | 6 |
| `KpiScoreCalculatorTest` | 6 |
| `KpiViewCompileTest` | 1 |

```bash
php artisan test --filter=Kpi
```

> ⚠️ `CreatesKpiSchema` **membangun skema sqlite dengan tangan**, tidak menjalankan migration. Kolom
> baru harus ditambahkan di dua tempat — migration dan berkas itu — atau test gagal dengan error
> "no such column" yang tidak jelas asalnya. Pernah kejadian saat `is_kpi_excluded` ditambahkan.

Catatan lain: sqlite tidak bisa `whereYear` pada string periode format `'Y-m'`.

# Konfirmasi Peta Rantai Kerja

Lembar konfirmasi untuk dibahas dengan **para Manajer dan HRD**. Isinya seluruh rantai kerja yang
sekarang tercatat di sistem: **13 rantai, 77 pasangan, 20 orang terlibat**.

Peta ini **tidak menentukan nilai KPI siapa pun** — murni peta alur kerja. Yang menentukan
penilaian adalah matriks relasi divisi dan daftar penilai silang, dibahas terpisah.

Sumber data: tabel `kpi_work_relations` (aktif), diambil 12 Agustus 2026.

> **12 Agustus 2026.** 58 → 73 → 101 → 98 → 79 → **77 pasangan**. Tidak ada
> lagi pasangan bertanda tebakan: lawan bicara RnD di Software akhirnya disebut ("semua Software"),
> jadi pertanyaan Q2 tertutup. Sembilan belas penanda pengawasan dicabut jadi perhitungan
> turunan, dan rantai "Desain untuk RnD" dihapus. Rincian di
> [Bagian E](#bagian-e--riwayat-perubahan).

---

## Cara membaca

Arah panah berarti **"menyerahkan atau mengoordinasikan sesuatu kepada"**. Arah dicatat karena
berguna saat menelusuri hambatan, tapi hubungannya tetap dua arah dalam praktik.

Kolom **Dasar** menandai dari mana pasangan itu berasal:

| Tanda | Jumlah | Arti | Yang perlu dilakukan |
|---|---|---|---|
| **D** | 26 | **Disebut langsung** — kedua sisi disebut namanya | cukup dibenarkan atau dibantah |
| **T** | 51 | **Turunan divisi** — divisinya yang disebut, orangnya diisi dari anggota divisi itu | **perlu dicek satu per satu** |
| **?** | 0 | **Tebakan** — lawan bicaranya tidak pernah disebut | sudah habis |

Pengawasan **tidak lagi jadi kategori pasangan.** Atasan yang mengetahui koordinasi anak buahnya
sekarang dihitung dari garis atasan saat halaman **Rantai Kerja** dibuka, jadi Leader ikut terbawa
tanpa penanda manual — lihat `docs/kpi-modul.md` §6.2.

---

## Bagian A — 13 rantai kerja

Nomor `R-01`…`R-13` hanya rujukan untuk rapat — sistem menyimpan rantai berdasarkan namanya.

### R-01 · Bahan produksi

Purchasing menyerahkan bahan yang akan diproduksi. **Dikoreksi:** bahan tidak turun ke lantai
produksi lewat Lina — hanya ke Leader dan Admin Production.

| Dari | Ke | Dasar |
|---|---|---|
| Lina Widiastuti — Purchasing (FAT) | Rhomadoni — Leader Production (Hardware) | D |
| Lina Widiastuti | Anisa Febriyanti — Admin Production (Hardware) | D |

Dicabut: Rasyid, Endarto, Alvian.

### R-02 · Barang riset & proyek

| Dari | Ke | Dasar |
|---|---|---|
| Lina Widiastuti — Purchasing (FAT) | Prastowo Dian Kristiyanto — RnD (Hardware) | D |
| Lina Widiastuti | Ilham Yoga Pratama — RnD (Hardware) | D |

### R-03 · Pembayaran klien & faktur

FAT mengurus pembayaran masuk dari klien dan faktur bersama Marketing.

| Dari | Ke | Dasar |
|---|---|---|
| Wahyu Nurul Haryanto — Manajer FAT | Dewi Setiawati — Manajer Marketing | T |
| Wahyu Nurul Haryanto | Akhmad Zaeni Mustofa — Corp. Account Manager (Mkt) | T |
| Wahyu Nurul Haryanto | Afif Faishahuda — Corp. Account Manager (Mkt) | T |
| Dewi Pusporini — Tax Officer (FAT) | Dewi Setiawati | T |
| Dewi Pusporini | Akhmad Zaeni Mustofa | T |
| Dewi Pusporini | Afif Faishahuda | T |
| Maritza Isyaura Putri Rizma — Accounting & Finance (FAT) | Dewi Setiawati | T |
| Maritza Isyaura Putri Rizma | Akhmad Zaeni Mustofa | T |
| Maritza Isyaura Putri Rizma | Afif Faishahuda | T |

**Masih perlu ditanyakan (Q5):** sembilan pasangan dari satu keterangan tingkat divisi. Kemungkinan
yang sebenarnya jalan lebih sedikit.

### R-04 · Invoice

Project Operation Admin mengurus invoice dengan Marketing. **Ditambah:** Dewi Pusporini dari sisi
pajak dan Maritza dari sisi finance.

| Dari | Ke | Dasar |
|---|---|---|
| Zainni Novena Santi — Project Operation Admin (FAT) | Dewi Setiawati — Manajer Marketing | T |
| Zainni Novena Santi | Akhmad Zaeni Mustofa (Mkt) | T |
| Zainni Novena Santi | Afif Faishahuda (Mkt) | T |
| Dewi Pusporini — Tax Officer (FAT) | Dewi Setiawati | T |
| Dewi Pusporini | Akhmad Zaeni Mustofa | T |
| Dewi Pusporini | Afif Faishahuda | T |
| Maritza Isyaura Putri Rizma — Accounting & Finance (FAT) | Dewi Setiawati | T |
| Maritza Isyaura Putri Rizma | Akhmad Zaeni Mustofa | T |
| Maritza Isyaura Putri Rizma | Afif Faishahuda | T |

### R-05 · SPK

Project Operation Admin mengoordinasikan SPK dengan Marketing, Hardware, dan **Software**.

| Dari | Ke | Dasar |
|---|---|---|
| Zainni Novena Santi (FAT) | Dewi Setiawati — Manajer Marketing | T |
| Zainni Novena Santi | Akhmad Zaeni Mustofa (Mkt) | T |
| Zainni Novena Santi | Afif Faishahuda (Mkt) | T |
| Zainni Novena Santi | Rhomadoni — Leader Production (Hardware) | T |
| Zainni Novena Santi | Anisa Febriyanti — Admin Production (Hardware) | T |
| Zainni Novena Santi | Fadel Muhammad Irsyad — Leader Software | D |
| Zainni Novena Santi | Nofiyanto — Manajer Software | D |

### R-06 · Harga modal di CRM

**Ditambah:** Zainni juga berurusan dengan Marketing, bukan hanya dengan pemegang CRM.

| Dari | Ke | Dasar |
|---|---|---|
| Zainni Novena Santi (FAT) | Shandy Bagus Ferdiansyah — Software | D |
| Maritza Isyaura Putri Rizma (FAT) | Shandy Bagus Ferdiansyah | D |
| Zainni Novena Santi | Dewi Setiawati — Manajer Marketing | T |
| Zainni Novena Santi | Akhmad Zaeni Mustofa (Mkt) | T |
| Zainni Novena Santi | Afif Faishahuda (Mkt) | T |

### R-07 · Website inventory

Rantai terpadat: **15 pasangan**. Software memegang sistemnya, Purchasing dan FAT ikut memakai.

| Dari | Ke | Dasar |
|---|---|---|
| Shandy Bagus Ferdiansyah — Software | Rhomadoni — Leader Production (Hardware) | T |
| Shandy Bagus Ferdiansyah | Rasyid Priyo Nugroho — Production (Hardware) | T |
| Shandy Bagus Ferdiansyah | Anisa Febriyanti — Admin Production (Hardware) | T |
| Shandy Bagus Ferdiansyah | Lina Widiastuti — Purchasing (FAT) | T |
| Shandy Bagus Ferdiansyah | Maritza Isyaura Putri Rizma — Accounting & Finance (FAT) | T |
| Shandy Bagus Ferdiansyah | Dewi Pusporini — Tax Officer (FAT) | D |
| Lina Widiastuti — Purchasing (FAT) | Rhomadoni | T |
| Lina Widiastuti | Anisa Febriyanti | T |
| Zainni Novena Santi — Project Operation Admin (FAT) | Shandy Bagus Ferdiansyah | D |

**Perlu dipastikan (Q8, baru):** "Lina ke produksi" kubaca sebagai Rhomadoni dan Anisa saja —
mengikuti koreksi bahan produksi di R-01 yang mencabut Rasyid dan kedua welder. Kalau di sistem
inventory Rasyid ternyata memang termasuk, satu baris perlu ditambah.

### R-08 · CRM penawaran

**Ditambah:** Shandy juga berurusan dengan Zainni.

| Dari | Ke | Dasar |
|---|---|---|
| Shandy Bagus Ferdiansyah — Software | Dewi Setiawati — Manajer Marketing | T |
| Shandy Bagus Ferdiansyah | Akhmad Zaeni Mustofa (Mkt) | T |
| Shandy Bagus Ferdiansyah | Afif Faishahuda (Mkt) | T |
| Shandy Bagus Ferdiansyah | Zainni Novena Santi — Project Operation Admin (FAT) | D |

### R-09 · HRIS absensi & payroll

**Ditambah:** koordinasi pajak payroll. Ini satu-satunya rantai yang **berangkat dari HRD**, bukan
menuju HRD.

| Dari | Ke | Dasar |
|---|---|---|
| Shandy Bagus Ferdiansyah — Software | Avissa Nova Fauzistika — HRD | T |
| Shandy Bagus Ferdiansyah | Maritza Isyaura Putri Rizma — Accounting & Finance (FAT) | T |
| Shandy Bagus Ferdiansyah | Dewi Pusporini — Tax Officer (FAT) | D |
| **Avissa Nova Fauzistika — HRD** | Dewi Pusporini — Tax Officer (FAT) | D |

Wahyu turun dari serah terima langsung (**T**) jadi pengawasan (**P**) sesuai koreksi.

### R-10 · Riset — pertanyaan Q2 tertutup

RnD berkoordinasi dengan **seluruh Software**. Tidak ada lagi tebakan di rantai ini.

| Dari | Ke | Dasar |
|---|---|---|
| Prastowo Dian Kristiyanto — RnD (Hardware) | Fadel Muhammad Irsyad — Leader Software | D |
| Prastowo Dian Kristiyanto | Shandy Bagus Ferdiansyah — Software | D |
| Prastowo Dian Kristiyanto | Tata Azkia Azzahra — UI/UX Designer (Software) | D |
| Prastowo Dian Kristiyanto | Akhmad Syarif Abdullah — Software | D |
| Prastowo Dian Kristiyanto | Nofiyanto — Manajer Software | D |
| Ilham Yoga Pratama — RnD (Hardware) | Fadel Muhammad Irsyad | D |
| Ilham Yoga Pratama | Shandy Bagus Ferdiansyah | D |
| Ilham Yoga Pratama | Tata Azkia Azzahra | D |
| Ilham Yoga Pratama | Akhmad Syarif Abdullah | D |
| Ilham Yoga Pratama | Nofiyanto | D |
| **Nofiyanto — Manajer Software** | Muhammad Subarkah — Manajer Hardware | D |

Rantai ini juga menutup separuh Q4: **Akhmad Syarif Abdullah** akhirnya punya relasi lintas divisi.

### R-11 · Laporan hasil riset

**Ditambah:** Anisa ikut melaporkan.

| Dari | Ke | Dasar |
|---|---|---|
| Prastowo Dian Kristiyanto — RnD | Avissa Nova Fauzistika — HRD | T |
| Prastowo Dian Kristiyanto | Wahyu Nurul Haryanto — Manajer FAT | T |
| Ilham Yoga Pratama — RnD | Avissa Nova Fauzistika | T |
| Ilham Yoga Pratama | Wahyu Nurul Haryanto | T |
| **Anisa Febriyanti — Admin Production (Hardware)** | Avissa Nova Fauzistika | D |
| **Anisa Febriyanti** | Wahyu Nurul Haryanto | D |

**Masih perlu ditanyakan (Q3):** kenapa laporan riset masuk ke HRD, bukan ke Manajer Hardware atau
Direksi?

### R-12 · Target penyelesaian proyek

**Dikoreksi:** Rasyid dicabut — target penyelesaian dibicarakan Marketing dengan Leader Production
dan Manajernya, tidak turun ke pelaksana.

| Dari | Ke | Dasar |
|---|---|---|
| Dewi Setiawati — Manajer Marketing | Rhomadoni — Leader Production | T |
| Akhmad Zaeni Mustofa (Mkt) | Rhomadoni | T |
| Afif Faishahuda (Mkt) | Rhomadoni | T |

### R-13 · Kustomisasi item

| Dari | Ke | Dasar |
|---|---|---|
| Dewi Setiawati — Manajer Marketing | Prastowo Dian Kristiyanto — RnD | T |
| Dewi Setiawati | Ilham Yoga Pratama — RnD | T |
| Akhmad Zaeni Mustofa (Mkt) | Prastowo Dian Kristiyanto | T |
| Akhmad Zaeni Mustofa | Ilham Yoga Pratama | T |
| Afif Faishahuda (Mkt) | Prastowo Dian Kristiyanto | T |
| Afif Faishahuda | Ilham Yoga Pratama | T |


---

## Bagian B — per divisi

Angka **Pasangan** adalah jumlah relasi orang-ke-orang yang orang itu punya di seluruh peta.
Baris **bertanda tebal** berarti belum masuk rantai mana pun.

### FAT & Supply Chain — Manajer: Wahyu Nurul Haryanto

| Orang | Jabatan | Pasangan | Rantai |
|---|---|---|---|
| Zainni Novena Santi | Project Operation Admin | 17 | Invoice, SPK, Harga modal CRM, Website inventory, CRM penawaran |
| Maritza Isyaura Putri Rizma | Accounting & Finance | 11 | Pembayaran & faktur, Invoice, Harga modal CRM, Website inventory, HRIS |
| Lina Widiastuti | Purchasing | 10 | Bahan produksi, Barang riset, Website inventory |
| Wahyu Nurul Haryanto | Manajer FAT | 9 | Pembayaran & faktur, Website inventory, HRIS, Laporan hasil riset |
| Dewi Pusporini | Tax Officer | 9 | Pembayaran & faktur, Invoice, Website inventory, HRIS |

### Hardware Division — Manajer: Muhammad Subarkah

| Orang | Jabatan | Pasangan | Rantai |
|---|---|---|---|
| Prastowo Dian Kristiyanto | RnD | 12 | Barang riset, Riset, Laporan hasil riset, Kustomisasi item, Desain untuk RnD |
| Ilham Yoga Pratama | RnD | 12 | Sama seperti Prastowo |
| Rhomadoni | Leader Production | 8 | Bahan produksi, SPK, Website inventory, Target penyelesaian |
| Anisa Febriyanti | Admin Production | 7 | Bahan produksi, SPK, Website inventory, Laporan hasil riset |
| Muhammad Subarkah | Manajer Hardware | 7 | Bahan produksi, Barang riset, SPK, Riset, Target penyelesaian — pengawasan |
| Rasyid Priyo Nugroho | Production | 2 | Website inventory |
| **Endarto Nugroho** | **Welder** | **0** | **— dicabut dari bahan produksi 12 Agu 2026** |
| **Alvian Riswandanu** | **Welder** | **0** | **— dicabut dari bahan produksi 12 Agu 2026** |

### Software Division — Manajer: Nofiyanto

| Orang | Jabatan | Pasangan | Rantai |
|---|---|---|---|
| Shandy Bagus Ferdiansyah | Software | 20 | Harga modal CRM, Website inventory, CRM penawaran, HRIS, Riset |
| Nofiyanto | Manajer Software | 15 | SPK, Website inventory, CRM penawaran, HRIS, Riset |
| Tata Azkia Azzahra | UI/UX Designer | 4 | Riset, Desain untuk RnD |
| Fadel Muhammad Irsyad | Leader Software | 3 | SPK, Riset |
| Akhmad Syarif Abdullah | Software | 2 | Riset |

### Marketing & Sales — Manajer: Dewi Setiawati

| Orang | Jabatan | Pasangan | Rantai |
|---|---|---|---|
| Dewi Setiawati | Manajer Marketing | 14 | Pembayaran & faktur, Invoice, SPK, Harga modal CRM, CRM penawaran, Target penyelesaian, Kustomisasi item |
| Akhmad Zaeni Mustofa | Corporate Account Manager | 14 | Sama seperti Dewi Setiawati |
| Afif Faishahuda | Corporate Account Manager | 14 | Sama seperti Dewi Setiawati |
| **F. X. Okka Septa Pratama** | **Publication Division** | **0** | **— belum masuk rantai mana pun, lihat Q4** |

### HRD & Corporate Service — HRD: Avissa Nova Fauzistika

| Orang | Jabatan | Pasangan | Rantai |
|---|---|---|---|
| Avissa Nova Fauzistika | HRD | 6 | HRIS, Laporan hasil riset |
| **Muhammad Fauzan** | **Supporting Staff** | **0** | **— lihat Q7** |
| **Muh Yusuf Kristanto** | **Security** | **0** | **— lihat Q7** |
| **Agung Prabowo** | **Security** | **0** | **— lihat Q7** |
| **Haryanto** | **Security — outsourcing** | **0** | **— lihat Q7** |

---

## Bagian C — pertanyaan

### Sudah terjawab

| No | Pertanyaan | Jawaban |
|---|---|---|
| **Q1** | Manajer Hardware & Software tidak muncul di rantai mana pun | Keduanya memang ikut, atas dasar pengawasan |
| **Q2** | Lawan bicara RnD di Software siapa? | Seluruh Software — Fadel, Shandy, Tata, Syarif, Nofiyanto |
| **Q6** | Welder ikut rantai bahan produksi? | Tidak. Rasyid dan kedua welder dicabut |

### Masih terbuka

**Q3 — Laporan hasil riset ke HRD, benar?** RnD dan Anisa melaporkan hasil riset ke Avissa (HRD) dan
Wahyu (FAT). Perlu dipastikan HRD memang penerima laporan riset, bukan Manajer Hardware atau Direksi.

**Q4 — Okka tidak punya relasi lintas divisi.** F. X. Okka Septa Pratama (Publication, Marketing)
masih 0 pasangan. Publication biasanya berurusan dengan Marketing dan Software soal materi. Separuh
Q4 sudah tertutup: Syarif kini masuk lewat rantai Riset.

**Q5 — Sembilan pasangan "Pembayaran klien & faktur" terlalu lebar?** Tiga orang FAT dikalikan tiga
orang Marketing, seluruhnya turunan dari satu keterangan tingkat divisi. Hal yang sama berlaku untuk
sembilan pasangan Invoice di R-04.

**Q7 — Empat orang HRD dan security tanpa relasi lintas divisi.** Fauzan, Yusuf, Agung, dan Haryanto
bekerja ke dalam. Kalau itu memang keadaannya, tidak ada yang perlu diubah.

**Q8 — Rasyid tinggal di website inventory saja. Benar?** Rasyid sudah dicabut dari bahan produksi
(R-01) dan target penyelesaian proyek (R-12). Yang tersisa dua pasangan di website inventory:
Shandy → Rasyid dan Nofiyanto → Rasyid. Dua pertanyaannya sekaligus:

- Apakah Rasyid memang memakai sistem inventory, atau itu pun lewat Rhomadoni dan Anisa?
- "Lina ke produksi" di rantai yang sama kubaca Rhomadoni dan Anisa saja, mengikuti koreksi R-01 —
  perlu dipastikan.

Kalau jawabannya Rasyid tidak memakai inventory, dia keluar dari peta sama sekali seperti kedua
welder.

**Q9 (baru) — Endarto dan Alvian sekarang tanpa relasi lintas divisi.** Keduanya welder dan hanya
pernah muncul di bahan produksi. Setelah dicabut, mereka tidak ada di peta. Wajar kalau welder
memang bekerja ke dalam — perlu dipastikan.

**Q10 (belum diputuskan) — cakupan pengawasan Manajer.** Subarkah dan Nofiyanto hanya masuk rantai
yang disebut manajemen, bukan setiap rantai yang diikuti anak buahnya.

---

## Bagian D — ringkasan angka

| Hal | Jumlah |
|---|---|
| Rantai kerja | 13 |
| Pasangan orang-ke-orang | 77 |
| Orang terlibat | 20 |
| Pasangan **disebut langsung** (D) | 26 |
| Pasangan **turunan divisi** (T) | 51 |
| Pasangan **tebakan** (?) | 0 |
| Pasangan dihapus | 6 |
| Karyawan aktif tanpa relasi lintas divisi | 7 (di luar Direksi & Superadmin) |
| Rantai terpadat | Riset — 11 dari 77 pasangan |
| Orang dengan relasi terbanyak | Shandy Bagus Ferdiansyah — 20 |
| Pertanyaan terjawab | 3 dari 10 |

---

## Bagian E — riwayat perubahan

### 12 Agustus 2026 — putaran ketiga (98 → 77)

| Perubahan | Δ |
|---|---|
| 19 penanda pengawasan dicabut dari tabel, jadi perhitungan turunan dari garis atasan | 98 → 79 |
| Rantai "Desain untuk RnD" (Tata → Prastowo, Ilham) dihapus | 79 → 77 |

Pengawasan tidak lagi disimpan sebagai pasangan. Sebabnya: "atasan mengetahui" bukan hubungan antar
dua orang melainkan akibat struktur organisasi, dan penandaan tangannya terbukti bolong — Fadel
(Leader Software, atasan langsung Shandy) tidak pernah tertandai padahal Nofiyanto yang dua tingkat
di atas tertandai. Sekarang Leader ikut terbawa otomatis.

### 12 Agustus 2026 — putaran kedua (73 → 101)

| Rantai | Perubahan | Δ |
|---|---|---|
| R-01 Bahan produksi | Anisa masuk; Rasyid, Endarto, Alvian dicabut | 4 → 3 |
| R-02 Barang riset | Subarkah (pengawasan) | 2 → 3 |
| R-04 Invoice | Dewi Pusporini (pajak) + Maritza (finance) ke Marketing | 3 → 9 |
| R-05 SPK | Fadel + Nofiyanto, perihal SPK software | 6 → 8 |
| R-06 Harga modal CRM | Zainni ke Marketing | 2 → 5 |
| R-07 Website inventory | Lina ke produksi; Shandy ke Dewi Pusporini; Zainni ke Shandy; Wahyu mengetahui | 10 → 15 |
| R-08 CRM penawaran | Shandy ke Zainni | 6 → 7 |
| R-09 HRIS | Shandy ke Dewi Pusporini; Avissa ke Dewi Pusporini; Wahyu jadi pengawasan | 6 → 8 |
| R-10 Riset | RnD ke seluruh Software; Nofiyanto ke Subarkah | 4 → 11 |
| R-11 Laporan hasil riset | Anisa ikut melaporkan | 4 → 6 |
| R-12 Target penyelesaian proyek | Rasyid dicabut | 9 → 6 |

Tidak berubah: R-03 dan R-13.

### 12 Agustus 2026 — putaran pertama (58 → 73)

Subarkah dan Nofiyanto masuk atas dasar pengawasan, menjawab Q1.

### 10 Agustus 2026 — peta awal (58)

Dibangun dari keterangan tingkat divisi. Empat pasangan di rantai Riset masih tebakan.

---

## Setelah konfirmasi

Perubahan hasil rapat ditulis ke `$workChains` di
[`database/seeders/KpiOrgWiringSeeder.php`](../database/seeders/KpiOrgWiringSeeder.php), lalu:

```bash
php artisan db:seed --class=KpiOrgWiringSeeder --force
```

> ⚠️ **Periode `Uji Coba Semester I 2026` sudah berstatus `open`.** Seeder masih aman dijalankan
> selama `kpi_cross_layer_a` dan `kpi_cross_layer_b` kosong. Begitu ada satu pengisian masuk,
> berhenti memakai seeder — ubah datanya lewat antarmuka admin.

Beberapa rantai punya **lebih dari satu baris berlabel sama**. Itu disengaja: pasangan dihasilkan
dari perkalian `from` × `to`, jadi satu baris tidak bisa menyatakan "Zainni ke Marketing" tanpa
sekaligus membuat "Maritza ke Marketing". Jangan digabungkan supaya rapi — itu akan memunculkan
pasangan yang tidak pernah dikonfirmasi siapa pun.

Pasangan yang dicabut **dihapus**; riwayatnya tersimpan di git pada berkas seeder, bukan di basis
data. Peta ini juga bisa disunting langsung di menu **Rantai Kerja**, dan hasilnya terlihat di menu
**Graf Relasi Divisi** pada saringan sisi **kerja**.

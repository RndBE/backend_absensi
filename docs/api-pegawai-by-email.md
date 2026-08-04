# API Pegawai — Cari berdasarkan Email

Endpoint service-to-service untuk sistem lain yang butuh identitas pegawai tanpa
menyalin tabelnya. Saat ini dipakai inventory (BE-INVENTORY) untuk pencatatan aset.

Kebalikan simetris dari `/api/hris/aset-karyawan` milik inventory: HRIS tetap
satu-satunya pemilik data pegawai, pemanggil membaca saat butuh dan tidak menyimpan
salinan, supaya tidak ada data basi.

## Endpoint

```
GET {HRIS_URL}/api/pegawai/by-email?email={email}
```

### Header wajib

```
X-API-KEY: <nilai HRIS_API_KEY>
Accept: application/json
```

Bearer token juga diterima sebagai alternatif. Nilai `HRIS_API_KEY` ada di `.env`
HRIS. Kirimkan lewat jalur yang aman — jangan ditulis di repo, tiket, atau chat grup.

### Parameter

| Nama | Wajib | Keterangan |
|---|---|---|
| `email` | ya | Email pegawai. Pencocokan tidak membedakan huruf besar/kecil. |

Kolom `email` ber-index unik di tabel `employees`, jadi tidak mungkin ada dua
pegawai dengan email sama.

## Contoh response

### 200 — ditemukan

```json
{
  "name": "SHANDY BAGUS FERDIANSYAH",
  "nomor_id": "004/SOFTW/XII/2025",
  "jabatan": "SOFTWARE DIVISION",
  "divisi": "SOFTWARE DIVISION"
}
```

Pemetaannya ke kolom HRIS:

| Field | Sumber |
|---|---|
| `name` | `employees.full_name` |
| `nomor_id` | `employees.employee_code` |
| `jabatan` | `employees.position` |
| `divisi` | `departments.name` lewat `employees.department_id` |

`jabatan` dan `divisi` bisa `null` kalau pegawai belum punya posisi atau belum
ditempatkan di departemen. `name` dan `nomor_id` selalu terisi.

### 422 — parameter kurang

```json
{ "success": false, "message": "Parameter email wajib diisi." }
```

### 404 — tidak ditemukan

```json
{ "success": false, "message": "Pegawai dengan email tersebut tidak terdaftar di HRIS." }
```

### 401 — token salah atau tidak dikirim

```json
{ "success": false, "message": "X-API-KEY tidak valid." }
```

### 503 — belum dikonfigurasi

```json
{ "success": false, "message": "API service HRIS belum dikonfigurasi (HRIS_API_KEY kosong)." }
```

Kunci kosong sengaja dijawab 503, bukan diloloskan. Gagal tertutup, bukan terbuka.

## Yang TIDAK dikembalikan

Hanya identitas dasar. Tidak ada payroll, gaji, NIK KTP, alamat, nomor rekening,
BPJS, NPWP, atau kontak — semuanya tidak relevan untuk pencatatan aset.

Perlu dicatat: endpoint ini **tidak** menyaring pegawai nonaktif. Pegawai yang sudah
resign tetap dibalas `200`. Kalau pemanggil perlu membedakannya, minta tambahan field
`is_active` — penambahan field bersifat aditif dan tidak merusak parser lama.

## Contoh pemanggilan

```bash
curl -H "X-API-KEY: $HRIS_API_KEY" \
     -H "Accept: application/json" \
     "https://hris.example.com/api/pegawai/by-email?email=budi@bejogja.com"
```

```php
// Sisi pemanggil (Laravel)
$response = Http::withHeaders(['X-API-KEY' => config('services.hris.api_key')])
    ->acceptJson()
    ->connectTimeout(3)
    ->timeout(5)
    ->get(config('services.hris.url') . '/api/pegawai/by-email', ['email' => $email]);

$pegawai = $response->successful() ? $response->json() : null;
```

Beri `timeout` dan tangani kegagalan dengan anggun — kalau HRIS sedang tidak bisa
dihubungi, halaman pemanggil sebaiknya tetap terbuka dengan keterangan bahwa data
pegawai gagal dimuat, bukan ikut gagal.

Jangan pakai `retry()` pada render halaman: dikalikan timeout, satu halaman bisa
menggantung belasan detik.

## Prasyarat data

Pencocokan memakai email, jadi email di HRIS dan di sistem pemanggil harus sama
(besar/kecil huruf tidak masalah). Saat ini 39 pegawai HRIS semuanya punya email.

## Berkas terkait

- Route: `routes/api.php` (grup `service.api`)
- Controller: `app/Http/Controllers/Api/PegawaiLookupController.php`
- Middleware: `app/Http/Middleware/ServiceApiKey.php` (alias `service.api` di `bootstrap/app.php`)
- Config: `config/services.php` → `services.hris_api.key`

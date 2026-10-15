<?php

/*
 * Surat Tugas. Nilai di sini mengikuti surat tugas Word yang dipakai HRD sepanjang 2026
 * (001–070/ST-ATC/2026), supaya surat dari sistem tidak berbeda bentuk dengan arsip lama.
 */
return [
    // Kode di tengah nomor surat: 001/ST-{code}/IX/2026.
    'number_code' => env('ASSIGNMENT_LETTER_CODE', 'ATC'),

    'default_city' => 'Yogyakarta',

    // Ditulis di blok "Bertindak Atas Nama" dan kalimat jarak ("... dari PT. Arta ...").
    // Sengaja tidak diambil dari tabel companies: alamat di sana memuat RT/RW dan kode pos,
    // sedangkan surat resmi memakai versi pendek ini.
    'company_name' => 'PT. Arta Teknologi Comunindo',
    'company_address' => 'Kadirojo I, Purwomartani, Kec. Kalasan, Kabupaten Sleman, Daerah Istimewa Yogyakarta',

    // Magang dan outsourcing tidak diberi surat tugas.
    'eligible_employment_statuses' => ['permanent', 'contract', 'probation'],

    // Kolom paraf yang selalu ada setelah kolom LV 2 per divisi peserta.
    'fixed_initial_boxes' => ['HRD', 'GA HSE'],

    // Karyawan yang dipasangi role "GA HSE" oleh GaHseSetupSeeder (nomor karyawan, bukan nama).
    // Ubah lewat env, dipisah koma: ASSIGNMENT_GA_HSE_EMPLOYEE_CODES="007/HRDCS/IX/2026,010/XXX/I/2025"
    // Bawaan: Latasha Nur Fienza Ramadhani (jabatan GA HSE, masuk September 2026). Karyawan
    // nonaktif di daftar ini dilewati seeder, jadi pergantian GA HSE cukup menambah nomor baru.
    'ga_hse_employee_codes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ASSIGNMENT_GA_HSE_EMPLOYEE_CODES', '007/HRDCS/IX/2026'))
    ))),

    'inspection_suggestions' => [
        'Baik, bisa digunakan',
        'Perlu perbaikan ringan, masih bisa digunakan',
        'Tidak layak digunakan',
    ],
];

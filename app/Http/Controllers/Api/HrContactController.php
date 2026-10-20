<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\HrContact;

/**
 * Kontak bantuan HRD untuk halaman login mobile. Sengaja publik (login belum punya token), jadi
 * hanya mengembalikan nomor WhatsApp dan email, tanpa identitas karyawan. Lihat HrContact.
 */
class HrContactController extends Controller
{
    public function show()
    {
        return response()->json([
            'success' => true,
            'data' => HrContact::resolve(),
        ]);
    }
}

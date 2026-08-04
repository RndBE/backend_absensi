<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pencarian data pegawai lewat email, untuk sistem lain yang butuh identitas
 * karyawan tanpa menyalin tabelnya (saat ini: inventory).
 *
 * Kebalikan simetris dari /api/hris/aset-karyawan milik inventory: HRIS tetap
 * satu-satunya pemilik data pegawai, pemanggil membaca saat butuh dan tidak
 * menyimpan salinan, supaya tidak ada data basi.
 *
 * Hanya membalas identitas dasar. Tidak ada payroll, gaji, NIK KTP, alamat,
 * rekening, atau kontak — data itu tidak relevan untuk pencatatan aset.
 */
class PegawaiLookupController extends Controller
{
    /**
     * GET /api/pegawai/by-email?email=budi@bejogja.com
     */
    public function byEmail(Request $request): JsonResponse
    {
        $email = trim((string) $request->query('email'));

        if ($email === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parameter email wajib diisi.',
            ], 422);
        }

        // Pencocokan tidak membedakan huruf besar/kecil. Kolom email ber-index
        // unik, jadi tidak mungkin ada dua pegawai dengan email sama.
        $employee = Employee::with('department')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai dengan email tersebut tidak terdaftar di HRIS.',
            ], 404);
        }

        return response()->json([
            'name'     => $employee->full_name,
            'nomor_id' => $employee->employee_code,
            'jabatan'  => $employee->position,
            'divisi'   => $employee->department?->name,
        ]);
    }
}

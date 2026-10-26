<?php

namespace App\Support;

use App\Models\BudgetRequest;

/**
 * Pesan penolakan pembuatan LPJ, dipakai portal karyawan dan API mobile. Yang boleh membuat
 * LPJ ditentukan BudgetRequest::scopeAwaitingLpjFrom; kelas ini hanya menjelaskan kenapa tidak.
 */
class LpjEligibility
{
    /**
     * Alasan $employeeId belum bisa membuat LPJ untuk anggaran yang lolos pencarian scope itu,
     * atau null bila anggarannya tidak ditemukan, belum disetujui, atau bukan miliknya
     * (pemanggil membalas "tidak ditemukan").
     */
    public static function whyNot(int $budgetId, int $employeeId): ?string
    {
        $budget = BudgetRequest::whereKey($budgetId)
            ->whereIn('status', ['approved', 'paid'])
            ->where(fn ($q) => $q
                ->where('employee_id', $employeeId)
                ->orWhereHas('participants', fn ($p) => $p->where('employees.id', $employeeId)))
            ->first();

        if (! $budget) {
            return null;
        }

        if ($budget->lpjs()->where('employee_id', $employeeId)->exists()) {
            return 'Anda sudah membuat LPJ untuk anggaran ini.';
        }

        return 'Anggaran ini tidak punya biaya atas nama Anda, jadi tidak ada LPJ yang perlu Anda buat. '
            .'Biaya bersama dilaporkan oleh pemilik anggaran.';
    }
}

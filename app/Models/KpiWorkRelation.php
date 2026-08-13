<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu rantai kerja antar dua orang: siapa menyerahkan apa kepada siapa.
 *
 * Tidak dipakai perhitungan KPI mana pun. Isinya peta alur kerja untuk ditinjau manusia —
 * lihat catatan di migration 2026_08_10_000016 soal kenapa dipisah dari relasi divisi.
 *
 * Satu "rantai" bukan baris, melainkan seluruh baris yang berbagi `label` yang sama. Sisi `from`
 * dan `to` sebuah rantai boleh berisi beberapa orang sekaligus, dan pasangannya adalah perkalian
 * keduanya — karena itu satu label bisa punya beberapa kelompok pasangan dengan sisi berbeda.
 *
 * Tidak ada penanda aktif/nonaktif: pasangan yang tidak berlaku lagi dihapus. Lihat migration
 * 2026_08_12_000018 soal kenapa penonaktifan dicabut.
 */
class KpiWorkRelation extends Model
{
    /** Baris hasil KpiOrgWiringSeeder — peta yang sudah dikonfirmasi manajemen. */
    public const SOURCE_SEEDER = 'seeder';

    /** Baris yang dibuat admin lewat halaman Rantai Kerja. */
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'company_id', 'from_employee_id', 'to_employee_id', 'label', 'source',
    ];

    public function from(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_employee_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_employee_id');
    }

    public function isFromSeeder(): bool
    {
        return $this->source === self::SOURCE_SEEDER;
    }

}

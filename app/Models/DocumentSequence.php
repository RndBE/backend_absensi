<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class DocumentSequence extends Model
{
    public const TYPE_ASSIGNMENT_LETTER = 'assignment_letter';

    protected $fillable = ['company_id', 'type', 'year', 'last_number'];

    /**
     * Ambil nomor urut berikutnya. Harus dipanggil di dalam transaksi supaya kunci baris
     * bertahan sampai nomor itu benar-benar tersimpan di dokumennya.
     */
    public static function next(int $companyId, string $type, int $year): int
    {
        $row = self::lockedRow($companyId, $type, $year);
        $number = $row->last_number + 1;

        DB::table('document_sequences')->where('id', $row->id)->update([
            'last_number' => $number,
            'updated_at' => now(),
        ]);

        return $number;
    }

    public static function lastNumber(int $companyId, string $type, int $year): int
    {
        return (int) self::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->where('year', $year)
            ->value('last_number');
    }

    public static function setLastNumber(int $companyId, string $type, int $year, int $lastNumber): void
    {
        DB::transaction(function () use ($companyId, $type, $year, $lastNumber) {
            $row = self::lockedRow($companyId, $type, $year);

            DB::table('document_sequences')->where('id', $row->id)->update([
                'last_number' => $lastNumber,
                'updated_at' => now(),
            ]);
        });
    }

    private static function lockedRow(int $companyId, string $type, int $year): object
    {
        $find = fn () => DB::table('document_sequences')
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($row = $find()) {
            return $row;
        }

        try {
            DB::table('document_sequences')->insert([
                'company_id' => $companyId,
                'type' => $type,
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Baris tahun ini baru saja dibuat oleh penerbitan lain; pakai baris itu.
        }

        return $find();
    }
}

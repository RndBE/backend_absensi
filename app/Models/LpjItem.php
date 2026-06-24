<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LpjItem extends Model
{
    /** Kategori pengeluaran — selaras dengan jenis item pada Pengajuan Anggaran. */
    public const CATEGORIES = [
        'transport'   => 'Transportasi',
        'meal'        => 'Makan',
        'lumpsum'     => 'Lumpsum',
        'entertain'   => 'Entertain',
        'operasional' => 'Operasional',
        'lainnya'     => 'Lainnya',
    ];

    protected $fillable = [
        'lpj_id', 'budget_request_item_id', 'uraian', 'kategori', 'satuan',
        'volume', 'harga_satuan', 'anggaran', 'realisasi',
        'bukti_file', 'keterangan', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'volume'       => 'decimal:2',
            'harga_satuan' => 'decimal:2',
            'anggaran'     => 'decimal:2',
            'realisasi'    => 'decimal:2',
        ];
    }

    public function lpj(): BelongsTo
    {
        return $this->belongsTo(Lpj::class);
    }

    public function budgetRequestItem(): BelongsTo
    {
        return $this->belongsTo(BudgetRequestItem::class);
    }

    public function getSelisihAttribute(): float
    {
        return (float) $this->anggaran - (float) $this->realisasi;
    }

    public function getKategoriLabelAttribute(): string
    {
        return self::CATEGORIES[$this->kategori] ?? ($this->kategori ?: '-');
    }
}

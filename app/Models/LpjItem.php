<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LpjItem extends Model
{
    protected $fillable = [
        'lpj_id', 'budget_request_item_id', 'uraian', 'satuan',
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
}

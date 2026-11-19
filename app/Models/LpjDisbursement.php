<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris uang yang ditransfer finance untuk sebuah LPJ. Tidak memengaruhi realisasi pengaju.
 */
class LpjDisbursement extends Model
{
    protected $fillable = ['lpj_id', 'transferred_at', 'amount', 'notes', 'proof_file', 'recorded_by', 'sort_order'];

    protected function casts(): array
    {
        return [
            'transferred_at' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function lpj(): BelongsTo
    {
        return $this->belongsTo(Lpj::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recorded_by');
    }
}

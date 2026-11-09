<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali finance mengoreksi realisasi LPJ. `before` & `after` berisi snapshot
 * {total, items: [{id, uraian, realisasi, keterangan}]} supaya karyawan dan approver berikutnya
 * melihat persis apa yang diubah.
 */
class LpjRevision extends Model
{
    protected $fillable = ['lpj_id', 'editor_id', 'step_order', 'before', 'after', 'notes'];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function lpj(): BelongsTo
    {
        return $this->belongsTo(Lpj::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'editor_id');
    }

    public function totalBefore(): float
    {
        return (float) ($this->before['total'] ?? 0);
    }

    public function totalAfter(): float
    {
        return (float) ($this->after['total'] ?? 0);
    }

    /**
     * Ringkasan perubahan per item yang realisasi atau keterangannya berbeda.
     *
     * @return array<int, string>
     */
    public function changes(): array
    {
        $before = collect($this->before['items'] ?? [])->keyBy('id');
        $lines = [];

        foreach ($this->after['items'] ?? [] as $item) {
            $old = $before->get($item['id']);

            if (! $old) {
                continue;
            }

            $parts = [];

            if ((float) $old['realisasi'] !== (float) $item['realisasi']) {
                $parts[] = self::rupiah($old['realisasi']).' → '.self::rupiah($item['realisasi']);
            }

            if ((string) $old['keterangan'] !== (string) $item['keterangan']) {
                $parts[] = 'keterangan: '.(filled($item['keterangan']) ? $item['keterangan'] : '(dikosongkan)');
            }

            if ($parts !== []) {
                $lines[] = ($item['uraian'] ?: 'Item').' ('.implode('; ', $parts).')';
            }
        }

        return $lines;
    }

    private static function rupiah($amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}

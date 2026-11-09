<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali finance mengubah rincian pengeluaran LPJ. `before` & `after` berisi snapshot
 * {total, items: [{id, kategori, uraian, satuan, volume, realisasi, keterangan, bukti}]} supaya pengaju
 * dan approver berikutnya melihat persis apa yang diubah.
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
     * Ringkasan perubahan per baris, siap tampil: ditambah, diubah, dihapus.
     *
     * @return array<int, array{kind: string, text: string}>
     */
    public function changes(): array
    {
        $before = collect($this->before['items'] ?? [])->keyBy('id');
        $after = collect($this->after['items'] ?? []);
        $lines = [];

        foreach ($after as $item) {
            $old = $before->get($item['id'] ?? null);

            if (! $old) {
                $lines[] = ['kind' => 'added', 'text' => self::label($item).' ('.self::rupiah($item['realisasi'] ?? 0).')'];

                continue;
            }

            $parts = [];

            if ((float) ($old['realisasi'] ?? 0) !== (float) ($item['realisasi'] ?? 0)) {
                $parts[] = self::rupiah($old['realisasi'] ?? 0).' → '.self::rupiah($item['realisasi'] ?? 0);
            }

            foreach (['kategori' => 'kategori', 'uraian' => 'uraian', 'satuan' => 'satuan', 'keterangan' => 'keterangan'] as $key => $name) {
                if (isset($old[$key], $item[$key]) && (string) $old[$key] !== (string) $item[$key]) {
                    $parts[] = $name.': '.(filled($item[$key]) ? $item[$key] : '(kosong)');
                }
            }

            if (isset($old['volume'], $item['volume']) && (float) $old['volume'] !== (float) $item['volume']) {
                $parts[] = 'volume: '.self::number($old['volume']).' → '.self::number($item['volume']);
            }

            if (isset($old['bukti'], $item['bukti']) && (string) $old['bukti'] !== (string) $item['bukti']) {
                $parts[] = filled($item['bukti']) ? 'bukti diganti' : 'bukti dilepas';
            }

            if ($parts !== []) {
                $lines[] = ['kind' => 'changed', 'text' => self::label($old).' ('.implode('; ', $parts).')'];
            }
        }

        $keptIds = $after->pluck('id')->filter()->all();
        foreach ($before as $id => $old) {
            if (! in_array($id, $keptIds, true)) {
                $lines[] = ['kind' => 'removed', 'text' => self::label($old).' ('.self::rupiah($old['realisasi'] ?? 0).')'];
            }
        }

        return $lines;
    }

    private static function label(array $item): string
    {
        $uraian = trim((string) ($item['uraian'] ?? ''));
        $kategori = LpjItem::CATEGORIES[$item['kategori'] ?? ''] ?? null;

        if ($kategori) {
            return $uraian !== '' ? "{$kategori} — {$uraian}" : $kategori;
        }

        return $uraian !== '' ? $uraian : 'Item';
    }

    private static function number($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }

    private static function rupiah($amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}

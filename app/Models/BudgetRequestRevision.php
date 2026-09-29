<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali approver mengubah item anggaran. `before` & `after` berisi snapshot
 * {total, items: [{id, type, description, amount}]} supaya pengaju dan approver
 * berikutnya bisa melihat persis apa yang diubah.
 */
class BudgetRequestRevision extends Model
{
    protected $fillable = [
        'budget_request_id', 'editor_id', 'step_order', 'before', 'after', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function budgetRequest(): BelongsTo
    {
        return $this->belongsTo(BudgetRequest::class);
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
     * Ringkasan perubahan per item, siap tampil: ditambah, diubah, dihapus.
     *
     * @return array<int, array{kind: string, text: string}>
     */
    public function changes(): array
    {
        $before = collect($this->before['items'] ?? [])->keyBy('id');
        $after = collect($this->after['items'] ?? []);
        $lines = [];

        foreach ($after as $item) {
            $old = isset($item['id']) ? $before->get($item['id']) : null;

            if (! $old) {
                $lines[] = ['kind' => 'added', 'text' => self::label($item).' ('.self::rupiah($item['amount']).')'];

                continue;
            }

            if (self::label($old) !== self::label($item) || (float) $old['amount'] !== (float) $item['amount']) {
                $text = self::label($old) === self::label($item)
                    ? self::label($item)
                    : self::label($old).' → '.self::label($item);
                $amount = (float) $old['amount'] === (float) $item['amount']
                    ? self::rupiah($item['amount'])
                    : self::rupiah($old['amount']).' → '.self::rupiah($item['amount']);
                $lines[] = ['kind' => 'changed', 'text' => "{$text} ({$amount})"];
            }
        }

        $keptIds = $after->pluck('id')->filter()->all();
        foreach ($before as $id => $old) {
            if (! in_array($id, $keptIds, true)) {
                $lines[] = ['kind' => 'removed', 'text' => self::label($old).' ('.self::rupiah($old['amount']).')'];
            }
        }

        return $lines;
    }

    private static function label(array $item): string
    {
        $type = (new BudgetRequestItem(['type' => $item['type'] ?? '']))->type_label;
        $description = trim((string) ($item['description'] ?? ''));

        return $description !== '' ? "{$type} — {$description}" : $type;
    }

    private static function rupiah($amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}

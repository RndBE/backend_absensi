<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu perubahan pada peta rantai kerja: siapa, dari mana, kapan, apa.
 *
 * Memuat perubahan dari kedua jalur — tautan tinjauan Manajer (`source = review`) dan halaman
 * admin internal (`source = admin`) — supaya riwayatnya tidak terpecah dua tempat.
 */
class KpiWorkChainEditLog extends Model
{
    public const SOURCE_REVIEW = 'review';

    public const SOURCE_ADMIN = 'admin';

    public const ACTION_ADD = 'add_pairs';

    public const ACTION_DELETE_PAIR = 'delete_pair';

    public const ACTION_CREATE_CHAIN = 'create_chain';

    public const ACTION_DELETE_CHAIN = 'delete_chain';

    protected $fillable = [
        'company_id', 'reviewer_id', 'actor_employee_id', 'source',
        'action', 'label', 'detail', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['detail' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'actor_employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(KpiWorkChainReviewer::class, 'reviewer_id');
    }

    /** Kalimat siap tampil untuk daftar riwayat. */
    public function summary(): string
    {
        $detail = $this->detail ?? [];

        return match ($this->action) {
            self::ACTION_ADD => sprintf('menambah %d pasangan', $detail['count'] ?? 0),
            self::ACTION_DELETE_PAIR => sprintf(
                'menghapus %s → %s',
                $detail['from'] ?? '?',
                $detail['to'] ?? '?'
            ),
            self::ACTION_CREATE_CHAIN => sprintf('membuat rantai dengan %d pasangan', $detail['count'] ?? 0),
            self::ACTION_DELETE_CHAIN => sprintf('menghapus rantai beserta %d pasangan', $detail['count'] ?? 0),
            default => $this->action,
        };
    }
}

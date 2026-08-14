<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu tautan tinjauan peta rantai kerja, milik satu Manajer, tanpa login.
 *
 * Token aslinya tidak pernah disimpan — hanya `sha256`-nya, sama seperti EmployeeMagicLink. Jadi
 * tautan yang sudah dibuat tidak bisa ditampilkan ulang; kalau hilang, terbitkan yang baru.
 */
class KpiWorkChainReviewer extends Model
{
    protected $fillable = [
        'company_id', 'employee_id', 'token_hash', 'expires_at', 'revoked_at',
        'last_used_at', 'use_count', 'last_ip_address', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
            'use_count' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function editLogs(): HasMany
    {
        return $this->hasMany(KpiWorkChainEditLog::class, 'reviewer_id');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && (bool) $this->employee?->is_active;
    }

    /** Alasan tautan tidak bisa dipakai, untuk ditampilkan ke penerimanya. Null berarti sah. */
    public function blockingReason(): ?string
    {
        if ($this->revoked_at !== null) {
            return 'Tautan ini sudah dicabut.';
        }

        if ($this->expires_at->isPast()) {
            return 'Tautan ini sudah kedaluwarsa.';
        }

        if (! $this->employee?->is_active) {
            return 'Akun pemilik tautan ini sudah tidak aktif.';
        }

        return null;
    }
}

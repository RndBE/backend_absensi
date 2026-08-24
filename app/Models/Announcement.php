<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pengumuman HR untuk Timeline dashboard karyawan.
 */
class Announcement extends Model
{
    protected $fillable = [
        'company_id', 'department_id', 'title', 'body',
        'published_at', 'expires_at', 'is_pinned', 'is_visible', 'created_by',
        'image_path', 'file_path', 'file_name', 'file_size', 'file_mime', 'link_url',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_pinned' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * Yang layak tampil pada satu saat: saklarnya menyala, tanggal berlakunya sudah lewat,
     * dan belum kedaluwarsa.
     *
     * Penyaringan tanggal dikerjakan DI SINI, saat dibaca — bukan oleh penjadwal. Itu
     * sebabnya "berlaku mulai" bisa diisi tanggal depan tanpa cron sama sekali: begitu
     * tanggalnya tiba, pengumuman muncul sendiri pada permintaan berikutnya. Penting,
     * karena `Schedule::command` memang tidak dijalankan di server ini.
     *
     * `expires_at` dipertahankan hanya untuk baris lama; pengumuman baru tidak mengisinya.
     */
    public function scopeTayang(Builder $query, ?Carbon $saat = null): Builder
    {
        $saat ??= Carbon::now();

        return $query
            ->where('is_visible', true)
            ->where('published_at', '<=', $saat)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $saat));
    }

    /** Sudah ditulis tapi tanggal berlakunya belum tiba. */
    public function belumBerlaku(?Carbon $saat = null): bool
    {
        return $this->published_at->greaterThan($saat ?? Carbon::now());
    }

    public function punyaLampiran(): bool
    {
        return (bool) ($this->image_path || $this->file_path || $this->link_url);
    }

    /** Ukuran berkas untuk dibaca manusia; pembaca berhak tahu sebelum menekan unduh. */
    public function ukuranBerkas(): ?string
    {
        if (! $this->file_size) {
            return null;
        }

        return $this->file_size >= 1048576
            ? round($this->file_size / 1048576, 1).' MB'
            : max(1, (int) round($this->file_size / 1024)).' KB';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyRegulation extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'category',
        'content',
        'effective_date',
        'is_active',
        'file_path',
        'file_name',
        'file_size',
        'file_mime',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CompanyRegulationAttachment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

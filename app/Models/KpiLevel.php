<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiLevel extends Model
{
    public const CATEGORY_EXCELLENCE = 'EX';

    public const CATEGORY_CONTRIBUTION = 'CO';

    public const CATEGORY_LEADERSHIP = 'LD';

    public const CATEGORIES = [
        self::CATEGORY_EXCELLENCE => 'General Excellence',
        self::CATEGORY_CONTRIBUTION => 'General Contribution',
        self::CATEGORY_LEADERSHIP => 'Leadership',
    ];

    protected $fillable = [
        'company_id', 'code', 'name', 'is_assessed',
        'weight_excellence', 'weight_contribution', 'weight_leadership',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_assessed' => 'boolean',
            'is_active' => 'boolean',
            'weight_excellence' => 'decimal:2',
            'weight_contribution' => 'decimal:2',
            'weight_leadership' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(KpiIndicator::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** @return array<string, float> kode kategori => bobot persen */
    public function categoryWeights(): array
    {
        return [
            self::CATEGORY_EXCELLENCE => (float) $this->weight_excellence,
            self::CATEGORY_CONTRIBUTION => (float) $this->weight_contribution,
            self::CATEGORY_LEADERSHIP => (float) $this->weight_leadership,
        ];
    }

    /**
     * Jumlah bobot tiga kategori. Wajib 100 sebelum periode boleh dibuka — pemeriksaannya
     * di FormRequest dan di pembuat snapshot, bukan constraint DB, karena admin perlu bisa
     * menyimpan keadaan setengah jadi saat menyetel ulang bobot.
     */
    public function totalCategoryWeight(): float
    {
        return array_sum($this->categoryWeights());
    }

    public function hasValidCategoryWeight(): bool
    {
        return abs($this->totalCategoryWeight() - 100.0) < 0.01;
    }
}

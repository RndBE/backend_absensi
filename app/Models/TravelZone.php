<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelZone extends Model
{
    protected $fillable = ['zone', 'name', 'min_km', 'max_km', 'meal_allowance'];

    protected function casts(): array
    {
        return [
            'meal_allowance' => 'decimal:2',
            'min_km' => 'integer',
            'max_km' => 'integer',
        ];
    }

    public static function findByKm(int $km): ?self
    {
        return static::where('min_km', '<=', $km)
            ->where(function ($q) use ($km) {
                $q->whereNull('max_km')->orWhere('max_km', '>=', $km);
            })
            ->orderBy('min_km', 'desc')
            ->first();
    }

    public function getKmRangeLabelAttribute(): string
    {
        if ($this->max_km === null) {
            return "≥ {$this->min_km} km";
        }

        return "{$this->min_km} – {$this->max_km} km";
    }
}

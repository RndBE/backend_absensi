<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BpjsSetting extends Model
{
    protected $fillable = ['key', 'value', 'effective_date', 'npp', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function getEffective(string $key, ?string $date = null): ?self
    {
        $date = $date ?? now()->format('Y-m-d');
        return static::where('key', $key)
            ->where('is_active', true)
            ->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->first();
    }
}

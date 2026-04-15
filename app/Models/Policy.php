<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    protected $fillable = ['key', 'name', 'value', 'description'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    /**
     * Get a policy value by key.
     */
    public static function getValue(string $key, float $default = 0): float
    {
        return (float) (static::where('key', $key)->value('value') ?? $default);
    }
}

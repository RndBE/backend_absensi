<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CityDistance extends Model
{
    protected $fillable = [
        'city_key', 'city_label', 'regency_id', 'distance_km', 'lat', 'lng', 'source',
    ];

    protected function casts(): array
    {
        return [
            'lat'         => 'float',
            'lng'         => 'float',
            'distance_km' => 'integer',
        ];
    }

    public static function normalizeKey(string $city): string
    {
        return Str::lower(trim($city));
    }
}

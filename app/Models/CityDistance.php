<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CityDistance extends Model
{
    protected $fillable = [
        'city_key', 'city_label', 'distance_km', 'lat', 'lng', 'source',
    ];

    public static function normalizeKey(string $city): string
    {
        return Str::lower(trim($city));
    }
}

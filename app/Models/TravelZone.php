<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelZone extends Model
{
    protected $fillable = ['zone', 'name', 'meal_allowance'];

    protected function casts(): array
    {
        return [
            'meal_allowance' => 'decimal:2',
        ];
    }
}

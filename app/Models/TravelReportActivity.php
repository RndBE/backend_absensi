<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelReportActivity extends Model
{
    protected $guarded = [];

    protected $casts = [
        'activity_date' => 'date',
        'results' => 'array',
    ];

    public function travelReport()
    {
        return $this->belongsTo(TravelReport::class);
    }

    public function documents()
    {
        return $this->hasMany(TravelReportDocument::class)->orderBy('sort_order');
    }
}

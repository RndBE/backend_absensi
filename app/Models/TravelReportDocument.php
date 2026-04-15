<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelReportDocument extends Model
{
    protected $guarded = [];

    protected $casts = [
        'activity_date' => 'date',
    ];

    public function travelReport()
    {
        return $this->belongsTo(TravelReport::class);
    }

    public function activity()
    {
        return $this->belongsTo(TravelReportActivity::class, 'travel_report_activity_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'clock_in', 'clock_out',
        'clock_in_lat', 'clock_in_lng', 'clock_out_lat', 'clock_out_lng',
        'clock_in_accuracy_meters', 'clock_out_accuracy_meters',
        'clock_in_is_mocked', 'clock_out_is_mocked',
        'clock_in_location_recorded_at', 'clock_out_location_recorded_at',
        'clock_in_photo', 'clock_out_photo', 'status', 'review_status',
        'suspicious_reason', 'security_flags', 'reviewed_by', 'reviewed_at',
        'review_notes', 'is_late',
        'is_remote', 'remote_notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_late' => 'boolean',
            'is_remote' => 'boolean',
            'clock_in_lat' => 'decimal:7',
            'clock_in_lng' => 'decimal:7',
            'clock_out_lat' => 'decimal:7',
            'clock_out_lng' => 'decimal:7',
            'clock_in_accuracy_meters' => 'decimal:2',
            'clock_out_accuracy_meters' => 'decimal:2',
            'clock_in_is_mocked' => 'boolean',
            'clock_out_is_mocked' => 'boolean',
            'clock_in_location_recorded_at' => 'datetime',
            'clock_out_location_recorded_at' => 'datetime',
            'security_flags' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }
}

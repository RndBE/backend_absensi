<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentLetterFacility extends Model
{
    public const TYPE_DEVICE = 'device';
    public const TYPE_TRANSPORT = 'transport';

    protected $fillable = [
        'assignment_letter_id', 'sort_order', 'type', 'vehicle_id', 'communication_device_id',
        'description', 'inspection_result', 'inspected_on', 'inspected_by',
    ];

    protected function casts(): array
    {
        return [
            'inspected_on' => 'date',
        ];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(AssignmentLetter::class, 'assignment_letter_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(CommunicationDevice::class, 'communication_device_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'inspected_by');
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_DEVICE ? 'Perangkat Komunikasi' : 'Transportasi';
    }

    /** "Baik, bisa digunakan (diperiksa pada tanggal 24 September 2026)" atau "-". */
    public function inspectionLabel(): string
    {
        if (blank($this->inspection_result)) {
            return '-';
        }

        return $this->inspected_on
            ? "{$this->inspection_result} (diperiksa pada tanggal ".AssignmentLetter::formatDate($this->inspected_on).')'
            : $this->inspection_result;
    }
}

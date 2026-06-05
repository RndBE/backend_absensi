<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePhotoArchive extends Model
{
    protected $fillable = [
        'company_id',
        'period',
        'status',
        'zip_file_name',
        'zip_file_path',
        'photo_count',
        'photo_paths',
        'drive_link',
        'generated_by',
        'archived_by',
        'generated_at',
        'archived_at',
        'local_photos_deleted_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'photo_paths' => 'array',
            'generated_at' => 'datetime',
            'archived_at' => 'datetime',
            'local_photos_deleted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'generated_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'archived_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePermissionOverride extends Model
{
    protected $fillable = ['employee_id', 'permission', 'allowed'];

    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

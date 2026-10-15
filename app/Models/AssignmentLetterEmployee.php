<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentLetterEmployee extends Model
{
    protected $fillable = [
        'assignment_letter_id', 'employee_id', 'sort_order',
        'name', 'employee_code', 'position', 'department_name',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(AssignmentLetter::class, 'assignment_letter_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

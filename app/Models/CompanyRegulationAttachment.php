<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyRegulationAttachment extends Model
{
    protected $fillable = [
        'company_regulation_id',
        'file_path',
        'file_name',
        'file_size',
        'file_mime',
    ];

    public function regulation(): BelongsTo
    {
        return $this->belongsTo(CompanyRegulation::class, 'company_regulation_id');
    }
}

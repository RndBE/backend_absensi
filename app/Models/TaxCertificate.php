<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxCertificate extends Model
{
    protected $fillable = [
        'employee_id', 'tax_year', 'certificate_number',
        'gross_annual', 'tax_annual', 'bpjs_annual', 'nett_annual',
        'monthly_details', 'status',
    ];

    protected function casts(): array
    {
        return [
            'gross_annual' => 'decimal:2',
            'tax_annual' => 'decimal:2',
            'bpjs_annual' => 'decimal:2',
            'nett_annual' => 'decimal:2',
            'monthly_details' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

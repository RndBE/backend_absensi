<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationDevice extends Model
{
    protected $fillable = ['company_id', 'code', 'name', 'is_active', 'notes'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Teks baris "Perangkat Komunikasi" di surat, mis. "HT 009/ATC/VIII/2024". */
    public function letterLabel(): string
    {
        return $this->code;
    }
}

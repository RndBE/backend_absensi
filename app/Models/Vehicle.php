<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    public const OWNERSHIPS = [
        'company' => 'milik perusahaan',
        'rental' => 'milik rental',
    ];

    protected $fillable = ['company_id', 'name', 'plate_number', 'ownership', 'is_active', 'notes'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Teks baris "Transportasi" di surat: "Alvez / milik perusahaan / K 1734 AT". */
    public function letterLabel(): string
    {
        return collect([
            $this->name,
            self::OWNERSHIPS[$this->ownership] ?? null,
            $this->plate_number,
        ])->filter(fn ($part) => filled($part))->implode(' / ');
    }
}

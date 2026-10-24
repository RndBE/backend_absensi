<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kabupaten/kota (kode wilayah BPS/Kemendagri). Kota tujuan perjalanan dipetakan ke sini supaya
 * jarak dan zonanya satu per kabupaten/kota, apa pun cara penulisannya.
 */
class Regency extends Model
{
    public $incrementing = false;

    protected $fillable = [
        'id', 'type', 'name', 'province', 'capital', 'lat', 'lng', 'distance_km', 'distance_source',
    ];

    protected function casts(): array
    {
        return [
            'lat'         => 'float',
            'lng'         => 'float',
            'distance_km' => 'integer',
        ];
    }

    /** "Kabupaten Kendal" / "Kota Semarang". */
    public function label(): string
    {
        return ($this->type === 'kota' ? 'Kota' : 'Kabupaten').' '.$this->name;
    }

    /** "Kabupaten Kendal, Jawa Tengah": dipakai sebagai isian kota tujuan dan untuk geocoding. */
    public function fullLabel(): string
    {
        return $this->label().', '.$this->province;
    }

    /**
     * Titik yang dipakai menghitung jarak: "Kota Semarang", atau "Ungaran (ibukota Kabupaten
     * Semarang)" untuk kabupaten.
     */
    public function distanceTarget(): string
    {
        return $this->type === 'kota' ? $this->label() : "{$this->capital} (ibukota {$this->label()})";
    }

    /** Bentuk JSON untuk app dan web. */
    public function toSummary(): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'name'       => $this->name,
            'province'   => $this->province,
            'capital'    => $this->capital,
            'label'      => $this->label(),
            'full_label' => $this->fullLabel(),
        ];
    }

    /**
     * Baris master dari database/data/regencies.php, siap di-insert.
     *
     * @return list<array{id: int, type: string, name: string, province: string, capital: string, created_at: mixed, updated_at: mixed}>
     */
    public static function masterRows(): array
    {
        $now = now();

        return array_map(fn (array $row) => [
            'id'         => $row[0],
            'type'       => $row[1],
            'name'       => $row[2],
            'province'   => $row[3],
            'capital'    => $row[4],
            'created_at' => $now,
            'updated_at' => $now,
        ], require database_path('data/regencies.php'));
    }
}

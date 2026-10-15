<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentLetterDestination extends Model
{
    protected $fillable = ['assignment_letter_id', 'sort_order', 'activity', 'address', 'distance_km'];

    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:1',
        ];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(AssignmentLetter::class, 'assignment_letter_id');
    }

    /** "8,3" atau "495" — koma desimal, tanpa ",0". */
    public function distanceLabel(): ?string
    {
        if ($this->distance_km === null) {
            return null;
        }

        $km = (float) $this->distance_km;

        return number_format($km, fmod($km, 1.0) == 0.0 ? 0 : 1, ',', '.');
    }

    /**
     * "Riset di Bendungan Ciawi yang beralamat di Jl. Panatraco, Bogor dan berjarak -/+ 590 km
     * dari PT. Arta Teknologi Comunindo." — pola kalimat di surat tugas Word.
     */
    public function sentence(): string
    {
        $text = rtrim(trim($this->activity), '.');
        $address = rtrim(trim((string) $this->address), '.');
        $company = config('assignment_letters.company_name');

        if ($address !== '') {
            $text .= " yang beralamat di {$address}";
        }

        if ($km = $this->distanceLabel()) {
            $text .= ($address !== '' ? ' dan berjarak' : ' yang berjarak')." -/+ {$km} km dari {$company}";
        }

        return $text.'.';
    }
}

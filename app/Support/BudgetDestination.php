<?php

namespace App\Support;

use App\Models\AssignmentLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Tujuan perjalanan yang disimpan di pengajuan anggaran: label, titik, dan asal jaraknya
 * (hasil travel/estimate-zone di app/web, atau surat tugas), untuk diperiksa approver.
 */
class BudgetDestination
{
    /** Asal jarak dan cara menyebutnya ke approver. */
    public const SOURCES = [
        'regency' => 'Ibukota kabupaten/kota',
        'place'   => 'Tempat dari pencarian',
        'map'     => 'Titik peta, dipilih sendiri',
        'text'    => 'Teks yang diketik',
        'letter'  => 'Surat tugas',
    ];

    private const COLUMNS = ['destination_label', 'destination_lat', 'destination_lng', 'distance_source', 'destination_regency_id'];

    public static function rules(): array
    {
        return [
            'destination_label'      => 'nullable|string|max:255',
            'destination_lat'        => 'nullable|numeric|between:-90,90|required_with:destination_lng',
            'destination_lng'        => 'nullable|numeric|between:-180,180|required_with:destination_lat',
            'distance_source'        => 'nullable|string|in:'.implode(',', array_keys(self::SOURCES)),
            'destination_regency_id' => 'nullable|integer',
        ];
    }

    /** Migrasi tujuan sudah dijalankan. */
    public static function ready(): bool
    {
        return Schema::hasColumn('budget_requests', 'distance_source');
    }

    /**
     * Kolom tujuan dari isian form. Tanpa jarak, tujuan ikut dikosongkan. [] bila migrasinya
     * belum dijalankan.
     */
    public static function attributes(Request $request, ?int $distanceKm): array
    {
        if (! self::ready()) {
            return [];
        }
        if ($distanceKm === null) {
            return array_fill_keys(self::COLUMNS, null);
        }

        return [
            'destination_label'      => $request->filled('destination_label') ? mb_substr(trim((string) $request->destination_label), 0, 255) : null,
            'destination_lat'        => $request->filled('destination_lat') ? (float) $request->destination_lat : null,
            'destination_lng'        => $request->filled('destination_lng') ? (float) $request->destination_lng : null,
            'distance_source'        => $request->input('distance_source') ?: null,
            'destination_regency_id' => $request->filled('destination_regency_id') ? (int) $request->destination_regency_id : null,
        ];
    }

    /** Surat tugas yang memuat jarak: tujuannya alamat lokasi terjauh di surat itu. */
    public static function letterAttributes(AssignmentLetter $letter): array
    {
        if (! self::ready()) {
            return [];
        }

        $farthest = $letter->destinations->whereNotNull('distance_km')->sortByDesc(fn ($d) => (float) $d->distance_km)->first();

        return [
            'destination_label'      => $farthest ? mb_substr((string) $farthest->address, 0, 255) : null,
            'destination_lat'        => null,
            'destination_lng'        => null,
            'distance_source'        => 'letter',
            'destination_regency_id' => null,
        ];
    }

    public static function sourceLabel(?string $source): ?string
    {
        return $source ? (self::SOURCES[$source] ?? $source) : null;
    }

    /** Tautan Google Maps ke titik tujuan, atau null bila titiknya tidak ada. */
    public static function mapUrl($lat, $lng): ?string
    {
        return $lat !== null && $lng !== null
            ? 'https://www.google.com/maps/search/?api=1&query='.((float) $lat).','.((float) $lng)
            : null;
    }
}

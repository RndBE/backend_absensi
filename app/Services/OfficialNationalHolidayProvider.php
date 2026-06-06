<?php

namespace App\Services;

class OfficialNationalHolidayProvider
{
    private const SOURCES = [
        2025 => [
            'reference' => 'SKB Menteri Agama Nomor 1017 Tahun 2024, Menteri Ketenagakerjaan Nomor 2 Tahun 2024, dan Menteri PANRB Nomor 2 Tahun 2024',
            'url' => 'https://jdih.menpan.go.id/dokumen-hukum/keputusan-bersama-menteri-agama-menteri-ketenagakerjaan-dan-menteri-pendayagunaan-aparatur-negara-1917',
            'holidays' => [
                '2025-01-01' => 'Tahun Baru 2025 Masehi',
                '2025-01-27' => 'Isra Mikraj Nabi Muhammad SAW',
                '2025-01-29' => 'Tahun Baru Imlek 2576 Kongzili',
                '2025-03-29' => 'Hari Suci Nyepi Tahun Baru Saka 1947',
                '2025-03-31' => 'Hari Raya Idul Fitri 1446 Hijriah',
                '2025-04-01' => 'Hari Raya Idul Fitri 1446 Hijriah',
                '2025-04-18' => 'Wafat Yesus Kristus',
                '2025-04-20' => 'Kebangkitan Yesus Kristus (Paskah)',
                '2025-05-01' => 'Hari Buruh Internasional',
                '2025-05-12' => 'Hari Raya Waisak 2569 BE',
                '2025-05-29' => 'Kenaikan Yesus Kristus',
                '2025-06-01' => 'Hari Lahir Pancasila',
                '2025-06-06' => 'Hari Raya Idul Adha 1446 Hijriah',
                '2025-06-27' => 'Tahun Baru Islam 1447 Hijriah',
                '2025-08-17' => 'Hari Proklamasi Kemerdekaan Republik Indonesia',
                '2025-09-05' => 'Maulid Nabi Muhammad SAW',
                '2025-12-25' => 'Kelahiran Yesus Kristus',
            ],
        ],
        2026 => [
            'reference' => 'SKB Menteri Agama Nomor 1497 Tahun 2025, Menteri Ketenagakerjaan Nomor 2 Tahun 2025, dan Menteri PANRB Nomor 5 Tahun 2025',
            'url' => 'https://jdih.menpan.go.id/dokumen-hukum/keputusan-bersama-menteri-agama-menteri-ketenagakerjaan-dan-menteri-pendayagunaan-aparatur-negara-2045',
            'holidays' => [
                '2026-01-01' => 'Tahun Baru 2026 Masehi',
                '2026-01-16' => 'Isra Mikraj Nabi Muhammad SAW',
                '2026-02-17' => 'Tahun Baru Imlek 2577 Kongzili',
                '2026-03-19' => 'Hari Suci Nyepi Tahun Baru Saka 1948',
                '2026-03-21' => 'Hari Raya Idul Fitri 1447 Hijriah',
                '2026-03-22' => 'Hari Raya Idul Fitri 1447 Hijriah',
                '2026-04-03' => 'Wafat Yesus Kristus',
                '2026-04-05' => 'Kebangkitan Yesus Kristus (Paskah)',
                '2026-05-01' => 'Hari Buruh Internasional',
                '2026-05-14' => 'Kenaikan Yesus Kristus',
                '2026-05-27' => 'Hari Raya Idul Adha 1447 Hijriah',
                '2026-05-31' => 'Hari Raya Waisak 2570 BE',
                '2026-06-01' => 'Hari Lahir Pancasila',
                '2026-06-16' => 'Tahun Baru Islam 1448 Hijriah',
                '2026-08-17' => 'Hari Proklamasi Kemerdekaan Republik Indonesia',
                '2026-08-25' => 'Maulid Nabi Muhammad SAW',
                '2026-12-25' => 'Kelahiran Yesus Kristus',
            ],
        ],
    ];

    public function forYear(int $year): array
    {
        return self::SOURCES[$year]['holidays'] ?? [];
    }

    public function referenceForYear(int $year): ?string
    {
        return self::SOURCES[$year]['reference'] ?? null;
    }

    public function sourceUrlForYear(int $year): ?string
    {
        return self::SOURCES[$year]['url'] ?? null;
    }

    public function availableYears(): array
    {
        return array_keys(self::SOURCES);
    }
}

<?php

namespace Tests\Unit;

use App\Services\OfficialNationalHolidayProvider;
use Tests\TestCase;

class OfficialNationalHolidayProviderTest extends TestCase
{
    public function test_2025_uses_official_skb_national_holiday_dates(): void
    {
        $holidays = app(OfficialNationalHolidayProvider::class)->forYear(2025);

        $this->assertCount(17, $holidays);
        $this->assertSame('Isra Mikraj Nabi Muhammad SAW', $holidays['2025-01-27']);
        $this->assertSame('Tahun Baru Imlek 2576 Kongzili', $holidays['2025-01-29']);
        $this->assertSame('Hari Suci Nyepi Tahun Baru Saka 1947', $holidays['2025-03-29']);
        $this->assertSame('Hari Raya Idul Fitri 1446 Hijriah', $holidays['2025-03-31']);
        $this->assertSame('Hari Raya Idul Fitri 1446 Hijriah', $holidays['2025-04-01']);
        $this->assertSame('Hari Raya Idul Adha 1446 Hijriah', $holidays['2025-06-06']);
        $this->assertSame('Tahun Baru Islam 1447 Hijriah', $holidays['2025-06-27']);
    }

    public function test_2026_uses_official_skb_national_holiday_dates(): void
    {
        $holidays = app(OfficialNationalHolidayProvider::class)->forYear(2026);

        $this->assertCount(17, $holidays);
        $this->assertSame('Isra Mikraj Nabi Muhammad SAW', $holidays['2026-01-16']);
        $this->assertSame('Tahun Baru Imlek 2577 Kongzili', $holidays['2026-02-17']);
        $this->assertSame('Hari Suci Nyepi Tahun Baru Saka 1948', $holidays['2026-03-19']);
        $this->assertSame('Hari Raya Idul Fitri 1447 Hijriah', $holidays['2026-03-21']);
        $this->assertSame('Hari Raya Idul Fitri 1447 Hijriah', $holidays['2026-03-22']);
        $this->assertSame('Hari Raya Idul Adha 1447 Hijriah', $holidays['2026-05-27']);
        $this->assertSame('Tahun Baru Islam 1448 Hijriah', $holidays['2026-06-16']);
        $this->assertSame('Maulid Nabi Muhammad SAW', $holidays['2026-08-25']);
    }

    public function test_unknown_year_returns_empty_list_instead_of_guessing_dates(): void
    {
        $this->assertSame([], app(OfficialNationalHolidayProvider::class)->forYear(2030));
    }
}

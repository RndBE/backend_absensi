<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Tessa\TessaActionController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Jejak "via Tessa" ditambahkan ke field teks yang tepat per jenis pengajuan.
 */
class TessaTagViaTessaTest extends TestCase
{
    private function tag(array $input, string $type): array
    {
        $method = new ReflectionMethod(TessaActionController::class, 'tagViaTessa');
        $method->setAccessible(true);

        return $method->invoke(new TessaActionController(), $input, $type);
    }

    public function test_reason_types_get_suffix(): void
    {
        foreach (['leave', 'overtime', 'attendance'] as $type) {
            $out = $this->tag(['reason' => 'Sakit'], $type);
            $this->assertSame('Sakit (via Tessa)', $out['reason'], $type);
        }
    }

    public function test_empty_field_gets_default(): void
    {
        $this->assertSame('Diajukan via Tessa', $this->tag([], 'leave')['reason']);
    }

    public function test_budget_tags_description_and_travel_tags_purpose(): void
    {
        $this->assertSame('Beli ATK (via Tessa)', $this->tag(['description' => 'Beli ATK'], 'budget')['description']);
        $this->assertSame('Survei lokasi (via Tessa)', $this->tag(['purpose' => 'Survei lokasi'], 'travel-report')['purpose']);
    }
}

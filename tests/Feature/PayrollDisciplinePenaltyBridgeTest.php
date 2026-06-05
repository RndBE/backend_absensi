<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PayrollRunController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

class PayrollDisciplinePenaltyBridgeTest extends TestCase
{
    public function test_payroll_fetches_daily_report_late_counts_from_daily_app(): void
    {
        config([
            'services.daily.url' => 'http://daily.test',
            'services.daily.internal_secret' => 'bridge-secret',
        ]);

        Http::fake([
            'http://daily.test/api/internal/payroll/daily-report-late*' => Http::response([
                'success' => true,
                'data' => [
                    ['email' => 'staff@example.test', 'late_days' => 2],
                    ['email' => 'other@example.test', 'late_days' => 0],
                ],
            ]),
        ]);

        $counts = $this->invokePrivate(
            new PayrollRunController,
            'fetchDailyReportLateCounts',
            [
                collect(['staff@example.test', 'other@example.test']),
                Carbon::parse('2026-06-01'),
                Carbon::parse('2026-06-30'),
            ]
        );

        $this->assertSame(2, $counts['staff@example.test']);
        $this->assertSame(0, $counts['other@example.test']);
        Http::assertSent(fn ($request) => $request->url() === 'http://daily.test/api/internal/payroll/daily-report-late?start=2026-06-01&end=2026-06-30&emails%5B0%5D=staff%40example.test&emails%5B1%5D=other%40example.test'
            && $request->header('X-Internal-Secret')[0] === 'bridge-secret');
    }

    public function test_payroll_builds_potongan_kedisiplinan_component(): void
    {
        $component = $this->invokePrivate(
            new PayrollRunController,
            'buildDisciplinePenaltyComponent',
            [3, 50000]
        );

        $this->assertSame('Potongan Kedisiplinan', $component['name']);
        $this->assertSame('deduction', $component['type']);
        $this->assertSame(150000.0, $component['amount']);
        $this->assertSame('3 hari × Rp 50.000', $component['detail']);
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}

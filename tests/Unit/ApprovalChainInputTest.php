<?php

namespace Tests\Unit;

use App\Support\ApprovalChainInput;
use PHPUnit\Framework\TestCase;

class ApprovalChainInputTest extends TestCase
{
    public function test_steps_remove_only_blank_values_and_reindex_the_chain(): void
    {
        $this->assertSame(
            [12, '  ', 34],
            ApprovalChainInput::steps(['', 12, null, '  ', 34])
        );
    }

    public function test_chains_normalize_each_array_and_preserve_malformed_values_for_validation(): void
    {
        $this->assertSame(
            [
                'budget' => [21],
                'travel_report' => 'not-an-array',
                'lpj' => [31, 32],
            ],
            ApprovalChainInput::chains([
                'budget' => ['', 21, null],
                'travel_report' => 'not-an-array',
                'lpj' => [31, '', 32],
            ])
        );
    }
}

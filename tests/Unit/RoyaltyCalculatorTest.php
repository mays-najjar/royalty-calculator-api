<?php

namespace Tests\Unit;

use App\Services\RoyaltyCalculator;
use PHPUnit\Framework\TestCase;

class RoyaltyCalculatorTest extends TestCase
{
    /**
     * The tiers used by most tests:
     *   1 - 1,000      -> 10%
     *   1,001 - 10,000 -> 15%
     *   10,001 and up  -> 20%
     *
     * @return array<int, array{min_units: int, max_units: int|null, percentage: float}>
     */
    private function tiers(): array
    {
        return [
            ['min_units' => 1, 'max_units' => 1000, 'percentage' => 10.0],
            ['min_units' => 1001, 'max_units' => 10000, 'percentage' => 15.0],
            ['min_units' => 10001, 'max_units' => null, 'percentage' => 20.0],
        ];
    }

    public function test_it_returns_zero_for_no_sales(): void
    {
        $result = (new RoyaltyCalculator)->calculate(0, 10.0, $this->tiers());

        $this->assertSame(0.0, $result['total']);
        $this->assertSame([], $result['breakdown']);
    }

    public function test_it_stays_inside_the_first_tier(): void
    {
        // 500 units x $10 = $5,000 revenue, all of it at 10%.
        $result = (new RoyaltyCalculator)->calculate(500, 10.0, $this->tiers());

        $this->assertSame(500.0, $result['total']);
        $this->assertCount(1, $result['breakdown']);
    }

    public function test_it_fills_the_first_tier_exactly(): void
    {
        // Boundary: 1,000 units must not spill into the second tier.
        $result = (new RoyaltyCalculator)->calculate(1000, 10.0, $this->tiers());

        $this->assertSame(1000.0, $result['total']);
        $this->assertCount(1, $result['breakdown']);
    }

    public function test_it_splits_across_two_tiers(): void
    {
        // 1,000 x $10 x 10% = $1,000
        //   500 x $10 x 15% = $750
        $result = (new RoyaltyCalculator)->calculate(1500, 10.0, $this->tiers());

        $this->assertSame(1750.0, $result['total']);
        $this->assertCount(2, $result['breakdown']);
        $this->assertSame(1000, $result['breakdown'][0]['units']);
        $this->assertSame(500, $result['breakdown'][1]['units']);
    }

    public function test_it_splits_across_all_three_tiers(): void
    {
        //  1,000 x $10 x 10% = $1,000
        //  9,000 x $10 x 15% = $13,500
        //  2,000 x $10 x 20% = $4,000
        $result = (new RoyaltyCalculator)->calculate(12000, 10.0, $this->tiers());

        $this->assertSame(18500.0, $result['total']);
        $this->assertCount(3, $result['breakdown']);
        $this->assertSame(2000, $result['breakdown'][2]['units']);
    }

    public function test_breakdown_rows_explain_each_tier(): void
    {
        $result = (new RoyaltyCalculator)->calculate(1500, 10.0, $this->tiers());
        $firstRow = $result['breakdown'][0];

        $this->assertSame(1, $firstRow['min_units']);
        $this->assertSame(1000, $firstRow['max_units']);
        $this->assertSame(10.0, $firstRow['percentage']);
        $this->assertSame(1000, $firstRow['units']);
        $this->assertSame(10000.0, $firstRow['revenue']);
        $this->assertSame(1000.0, $firstRow['royalty']);
    }

    public function test_it_handles_a_price_with_decimals(): void
    {
        // 200 units x $9.99 = $1,998 revenue, at 10% = $199.80
        $result = (new RoyaltyCalculator)->calculate(200, 9.99, $this->tiers());

        $this->assertEqualsWithDelta(199.80, $result['total'], 0.001);
    }
}

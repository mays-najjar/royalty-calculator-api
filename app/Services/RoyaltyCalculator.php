<?php

namespace App\Services;

/**
 * Calculates artist royalties using cumulative tiers.
 *
 * Example tiers:
 *   tier 1: units      1 - 1,000   -> 10%
 *   tier 2: units  1,001 - 10,000  -> 15%
 *   tier 3: units 10,001 - null    -> 20%
 *
 * "Cumulative" means each tier only applies to the units that fall inside it,
 * the way income tax brackets work. 1,500 units are therefore split into
 * 1,000 units at 10% and 500 units at 15% - not 1,500 units at 15%.
 */
class RoyaltyCalculator
{
    /**
     * @param  int  $units  Units sold. Must be zero or greater.
     * @param  float  $unitPrice  Price of a single unit, in the release currency.
     * @param  array<int, array{min_units: int, max_units: int|null, percentage: float}>  $tiers
     *                                Tiers ordered by min_units ascending. A null
     *                                max_units means the tier has no upper bound.
     * @return array{
     *     total: float,
     *     breakdown: array<int, array{
     *         min_units: int,
     *         max_units: int|null,
     *         percentage: float,
     *         units: int,
     *         revenue: float,
     *         royalty: float
     *     }>
     * }
     */
    public function calculate(int $units, float $unitPrice, array $tiers): array
    {
        // Nothing sold means nothing owed, and no rows to explain.
        if ($units <= 0) {
            return ['total' => 0.0, 'breakdown' => []];
        }

        $breakdown = [];
        $total = 0.0;
        $remaining = $units;

        foreach ($tiers as $tier) {
            // Every sold unit has been placed in a tier already.
            if ($remaining <= 0) {
                break;
            }

            $min = (int) $tier['min_units'];
            $max = $tier['max_units'] === null ? null : (int) $tier['max_units'];

            // This tier starts above what was sold, so neither it nor any
            // tier after it can apply.
            if ($units < $min) {
                break;
            }

            // How many units this tier can hold. The last tier is open ended.
            $capacity = $max === null ? PHP_INT_MAX : ($max - $min + 1);

            // Only the units that actually fall inside this tier.
            $unitsInTier = min($remaining, $capacity);

            $revenue = $unitsInTier * $unitPrice;

            // Multiply before dividing: 10000.0 * 10 / 100 is exactly 1000.0,
            // while 10000.0 * (10 / 100) drifts, because 0.1 has no exact
            // binary representation.
            $royalty = round($revenue * $tier['percentage'] / 100, 2);

            $breakdown[] = [
                'min_units' => $min,
                'max_units' => $max,
                'percentage' => (float) $tier['percentage'],
                'units' => $unitsInTier,
                'revenue' => round($revenue, 2),
                'royalty' => $royalty,
            ];

            $total += $royalty;
            $remaining -= $unitsInTier;
        }

        // If the tiers do not cover every unit sold, the uncovered units earn
        // nothing. That is a gap in the contract rules, not a calculation
        // error, so the caller can spot it by comparing the units in the
        // breakdown against the units passed in.
        return [
            'total' => round($total, 2),
            'breakdown' => $breakdown,
        ];
    }
}

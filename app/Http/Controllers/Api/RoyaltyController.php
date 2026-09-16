<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateRoyaltyRequest;
use App\Models\Release;
use App\Services\RoyaltyCalculator;
use Illuminate\Http\JsonResponse;

class RoyaltyController extends Controller
{
    /**
     * POST /api/releases/{release}/royalties/calculate
     */
    public function calculate(
        CalculateRoyaltyRequest $request,
        Release $release,
        RoyaltyCalculator $calculator
    ): JsonResponse {
        $tiers = $release->royaltyRules()
            ->orderBy('min_units')
            ->get()
            ->map(fn ($rule) => [
                'min_units' => (int) $rule->min_units,
                'max_units' => $rule->max_units === null ? null : (int) $rule->max_units,
                'percentage' => (float) $rule->percentage,
            ])
            ->all();

        if ($tiers === []) {
            return response()->json([
                'message' => 'This release has no royalty rules configured.',
            ], 422);
        }

        // Fall back to the units actually sold when the caller does not send any.
        $units = $request->integer('units', $release->sales()->sum('units'));

        $result = $calculator->calculate($units, (float) $release->unit_price, $tiers);

        return response()->json([
            'release' => [
                'id' => $release->id,
                'title' => $release->title,
                'unit_price' => (float) $release->unit_price,
            ],
            'units' => $units,
            'total_royalty' => $result['total'],
            'breakdown' => $result['breakdown'],
            // JSON_PRESERVE_ZERO_FRACTION keeps money as 1750.0 instead of 1750,
            // so clients always parse the same type for the same field.
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}

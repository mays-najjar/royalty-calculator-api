<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Release;
use App\Models\RoyaltyRule;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoyaltyEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function releaseWithTiers(float $unitPrice = 10.0): Release
    {
        $artist = Artist::create([
            'name' => 'Fairuz',
            'email' => 'artist@example.com',
        ]);

        $release = Release::create([
            'artist_id' => $artist->id,
            'title' => 'Live in Baalbek',
            'unit_price' => $unitPrice,
        ]);

        foreach ([
            ['min_units' => 1, 'max_units' => 1000, 'percentage' => 10.0],
            ['min_units' => 1001, 'max_units' => 10000, 'percentage' => 15.0],
            ['min_units' => 10001, 'max_units' => null, 'percentage' => 20.0],
        ] as $tier) {
            RoyaltyRule::create($tier + ['release_id' => $release->id]);
        }

        return $release;
    }

    public function test_it_calculates_royalties_for_the_units_sent(): void
    {
        $release = $this->releaseWithTiers();

        $response = $this->postJson("/api/releases/{$release->id}/royalties/calculate", [
            'units' => 1500,
        ]);

        $response->assertOk()
            ->assertJsonPath('units', 1500)
            ->assertJsonPath('total_royalty', 1750.0)
            ->assertJsonCount(2, 'breakdown');
    }

    public function test_it_falls_back_to_recorded_sales(): void
    {
        $release = $this->releaseWithTiers();

        Sale::create(['release_id' => $release->id, 'units' => 600, 'sold_at' => '2026-01-10']);
        Sale::create(['release_id' => $release->id, 'units' => 900, 'sold_at' => '2026-02-10']);

        $response = $this->postJson("/api/releases/{$release->id}/royalties/calculate");

        $response->assertOk()
            ->assertJsonPath('units', 1500)
            ->assertJsonPath('total_royalty', 1750.0);
    }

    public function test_it_rejects_negative_units(): void
    {
        $release = $this->releaseWithTiers();

        $this->postJson("/api/releases/{$release->id}/royalties/calculate", ['units' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('units');
    }

    public function test_it_rejects_units_that_are_not_a_number(): void
    {
        $release = $this->releaseWithTiers();

        $this->postJson("/api/releases/{$release->id}/royalties/calculate", ['units' => 'many'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('units');
    }

    public function test_it_returns_422_when_the_release_has_no_tiers(): void
    {
        $artist = Artist::create(['name' => 'Unknown', 'email' => null]);
        $release = Release::create([
            'artist_id' => $artist->id,
            'title' => 'No rules yet',
            'unit_price' => 10.0,
        ]);

        $this->postJson("/api/releases/{$release->id}/royalties/calculate", ['units' => 10])
            ->assertStatus(422);
    }

    public function test_it_returns_404_for_a_release_that_does_not_exist(): void
    {
        $this->postJson('/api/releases/9999/royalties/calculate', ['units' => 10])
            ->assertStatus(404);
    }
}

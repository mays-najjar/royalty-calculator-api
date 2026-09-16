<?php

namespace Database\Seeders;

use App\Models\Artist;
use App\Models\Release;
use App\Models\RoyaltyRule;
use App\Models\Sale;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $artist = Artist::create([
            'name' => 'Fairuz',
            'email' => 'fairuz@example.com',
        ]);

        $release = Release::create([
            'artist_id' => $artist->id,
            'title' => 'Live in Baalbek',
            'unit_price' => 10.00,
        ]);

        // Cumulative tiers: each rate applies only to the units inside its band.
        foreach ([
            ['min_units' => 1, 'max_units' => 1000, 'percentage' => 10.00],
            ['min_units' => 1001, 'max_units' => 10000, 'percentage' => 15.00],
            ['min_units' => 10001, 'max_units' => null, 'percentage' => 20.00],
        ] as $tier) {
            RoyaltyRule::create($tier + ['release_id' => $release->id]);
        }

        // 1,500 units sold in total -> 1,000 at 10% and 500 at 15%.
        Sale::create(['release_id' => $release->id, 'units' => 600, 'sold_at' => '2026-01-10']);
        Sale::create(['release_id' => $release->id, 'units' => 900, 'sold_at' => '2026-02-10']);
    }
}

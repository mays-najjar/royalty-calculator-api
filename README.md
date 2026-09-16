# Royalty Calculator API

A small Laravel REST API that calculates artist royalties from tiered contract rules.

Rates are **cumulative**: each rate applies only to the units that fall inside its own
band, the way income tax brackets work. With the tiers below, 1,500 units are billed as
1,000 units at 10% plus 500 units at 15% — not as 1,500 units at 15%.

| Tier | Units | Rate |
|---|---|---|
| 1 | 1 – 1,000 | 10% |
| 2 | 1,001 – 10,000 | 15% |
| 3 | 10,001 and up | 20% |

## Stack

- PHP 8.4, Laravel 13
- SQLite (swap to MySQL or PostgreSQL by changing `DB_*` in `.env`)
- PHPUnit

## Setup

```bash
composer install
php artisan migrate:fresh --seed
php artisan serve
```

The seeder creates one artist, one release priced at $10.00, the three tiers above, and
1,500 units of recorded sales.

## Endpoint

```
POST /api/releases/{release}/royalties/calculate
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `units` | integer ≥ 0 | no | Defaults to the units recorded in the `sales` table for that release |

### Request

```bash
curl -X POST http://localhost:8000/api/releases/1/royalties/calculate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"units\": 1500}"
```

### Response `200`

```json
{
  "release": { "id": 1, "title": "Live in Baalbek", "unit_price": 10.0 },
  "units": 1500,
  "total_royalty": 1750.0,
  "breakdown": [
    { "min_units": 1, "max_units": 1000, "percentage": 10.0, "units": 1000, "revenue": 10000.0, "royalty": 1000.0 },
    { "min_units": 1001, "max_units": 10000, "percentage": 15.0, "units": 500, "revenue": 5000.0, "royalty": 750.0 }
  ]
}
```

The breakdown ships with every response, because the first question asked about a royalty
statement is "why is it this number?".

### Other responses

| Status | When |
|---|---|
| `422` | `units` is negative or is not a whole number |
| `422` | The release has no royalty rules configured |
| `404` | The release does not exist |

## Data model

```
artists ──< releases ──< royalty_rules
                     └─< sales
```

- Money is stored as `decimal(10,2)`, never as a float.
- `royalty_rules.max_units` is nullable; `null` means the top tier has no ceiling.
- `royalty_rules` has a unique key on `(release_id, min_units)`, so one release cannot have
  two tiers starting at the same point.

## Tests

```bash
php artisan test
```

Routing, validation, the 404/422 paths and the sales fallback are covered by feature tests.
The tier maths is covered by unit tests that run without touching the database.

## Status

Work in progress: `App\Services\RoyaltyCalculator::calculate()` is not implemented yet, so
the tier tests fail by design until it is written.

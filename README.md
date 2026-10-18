<div align="center">

<img src="public/images/logo.png" alt="Dot.Farms" width="220" />

<br /><br />

**The agriculture ERP for the Dot Ecosystem — farm, field, and crop-cycle tracking from paddock to gate.**

<br />

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white) ![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat-square&logo=php&logoColor=white) ![Jetstream](https://img.shields.io/badge/Jetstream-Teams-4F46E5?style=flat-square) ![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?style=flat-square&logo=postgresql&logoColor=white)

<br /><br />

**Part of the Dot Ecosystem** &nbsp;·&nbsp; `farms.infodot.app`

</div>

---

## What is Dot.Farms?

Dot.Farms is the agriculture ERP for the Dot Ecosystem: crop planning, planting and harvest
execution, and yield tracking for farm owners, agronomists, and field operators. It owns the
agriculture domain end-to-end from paddock to gate. Downstream commerce — produce listing and
settlement — belongs to Dot.Emall and Dot.Billing; Dot.Farms hands off the moment produce is
harvest-ready. See `wiki.md` for the full platform description.

## What it does

**Records**
- Team-scoped farm registry (Jetstream Teams: every farm belongs to exactly one team)
- Fields per farm (soil type, moisture zone, size in hectares) and a reusable team crop catalog
- Crop cycles: one crop, on one field, in one season, with planting and harvest records
- Edit and delete for farms, fields, crops, crop cycles, plantings and harvests. Deletes are soft. Deleting a farm, field or cycle that has things under it asks for confirmation and removes them with it. A crop that cycles use cannot be deleted.

**Cycle lifecycle** (one state machine, `App\Enums\CropCycleStatus`, enforced by `App\Services\Farms\CropCycleLifecycle`)
- `planned` -> `planted` (recording a planting) -> `growing` (an explicit "Mark growing") -> `harvested` (an explicit "Close as harvested"), or `failed` / `abandoned` at any open point, both with a reason; a closed cycle can be re-opened
- Harvests are accepted only while a cycle is planted or growing, and may be partial; they never close the cycle by themselves
- Correcting a harvest or planting, or deleting one, re-derives the cycle's status and planting date; every record + status change is one database transaction

**Units**
- Quantities are stored with a unit from a fixed list (kg, t, bag, crate, bunch, l, head; ha for planted area; seed and seedling counts for plantings)
- kg and t are converted and summed together; every other unit is totalled on its own. Mixed units are never added.

**Reports** (`/reports`, `/harvests`)
- Yield per field per season, per hectare where the area is known (planted hectares if recorded, otherwise the field size)
- Harvest totals per crop and month, and outstanding cycles with overdue ones first
- Harvest list with season/crop/farm/date filters and totals, and a CSV export of harvest records (`/harvests/export`, honours the same filters)

**Access**
- Team owner and `admin`: everything. `editor`: plan and edit cycles, move them through their lifecycle, record and correct plantings and harvests; cannot delete anything or change farms, fields and crops. Any other member: read-only. The full table is in `app/Policies/Concerns/ChecksFarmRoles.php`.
- Every query for fields, cycles and records is scoped to the signed-in user's current team through the farm they belong to (`forTeam()`), failing closed: no current team, no rows. Console commands and queued jobs have no signed-in user and must scope explicitly with `forTeam($team)`.

**Platform**
- In-app notification bell (Laravel's `database` channel, live over Reverb), sent after the harvest transaction commits
- Ecosystem SSO sign-in through InfoDot

Not built: yield forecasting, weather integration, moisture/sensor telemetry, record restore (deletes are soft but there is no undelete screen), outbound event publishing to Dot.Brain/Dot.Emall (the domain models describe the trigger points; nothing fires yet).

## Domain Models

- **Farm**: tenant root (`team_id`)
- **Field**: a paddock within a farm; soil type, moisture zone, size
- **Crop**: team-owned catalog entry (e.g. "Maize, Yellow dent"), reused across fields and seasons
- **CropCycle**: one crop, one field, one season (`season` is a first-class column; see the migration's docblock for why)
- **PlantingRecord** / **HarvestRecord**: operational logs against a crop cycle

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3 |
| Frontend | Blade + Alpine.js 3 · Livewire 3 (notification bell) · Tailwind CSS |
| Database | PostgreSQL (shared across ecosystem) |
| Auth | Laravel Sanctum (ecosystem SSO) + Jetstream/Fortify (teams, 2FA) |
| Queue | Database queue driver |

## Quick Start

```bash
git clone https://github.com/sakhilebhayi/Dot.Farms.git
cd Dot.Farms
cp .env.example .env
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate
php artisan serve
```

`php artisan db:seed` seeds nothing and is safe anywhere. For a local demo dataset (a fake two-farm
team with a `test@example.com` user), run `php artisan db:seed --class=DemoFarmSeeder`; it refuses to
run unless `APP_ENV` is `local` or `testing`.

### Running Tests

```bash
php artisan test
```

Feature tests use an in-memory SQLite connection (see `phpunit.xml`) and Laravel's RefreshDatabase
trait, so no Postgres instance is needed to run them. The report queries and the migration are written
to be portable between SQLite and PostgreSQL; the suite only exercises SQLite.

## Ecosystem

**Dot.Farms** is one of the platforms in the Dot Ecosystem, connected via shared PostgreSQL and
Sanctum SSO, and via Knowledge Packs published to [Dot.Brain](https://github.com/sakhilebhayi/Dot.Brain).
See `wiki.md` for the full architecture and cross-platform relationships.

## License

MIT

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
- Restoring removed records (see **Removed records** below) is owner and admin only, like deleting.
- Every query for fields, cycles and records is scoped to the signed-in user's current team through the farm they belong to (`forTeam()`), failing closed: no current team, no rows. Console commands and queued jobs have no signed-in user and must scope explicitly with `forTeam($team)`.

**Platform**
- In-app notification bell (Laravel's `database` channel, live over Reverb), sent after the harvest transaction commits
- Ecosystem SSO sign-in through InfoDot

Not built: yield forecasting, weather integration, moisture/sensor telemetry, outbound event publishing to Dot.Brain/Dot.Emall (the domain models describe the trigger points; nothing fires yet).

## Roles

Access follows the team (company) role. InfoDot is the source of truth and the role is re-applied at every sign-in.

| InfoDot role | Dot.Farms role | Can |
|---|---|---|
| owner, admin | admin (owner for the creator) | everything: farms, fields and crops; delete cycles, plantings and harvests; restore anything removed |
| editor | editor | run the season: plan and edit crop cycles, move them through their lifecycle, record and correct plantings and harvests; read and export |
| viewer | viewer | read only: farms, fields, crops, cycles, harvests and the reports, and the harvest CSV export, exactly the read surface an editor has; no write control is shown and every write route answers 403 |
| anything else | viewer | an unrecognised InfoDot role gets the least powerful role, never editor |

The harvest CSV export is open to viewers because it is the same data the harvest list and the reports show and editors may export it. It is a single ability, `HarvestRecordPolicy::export()`, if that ever needs to be narrowed. The role matrix is in `tests/Feature/Farms/ViewerRoleMatrixTest.php`.

## Domain Models

- **Farm**: tenant root (`team_id`)
- **Field**: a paddock within a farm; soil type, moisture zone, size
- **Crop**: team-owned catalog entry (e.g. "Maize, Yellow dent"), reused across fields and seasons
- **CropCycle**: one crop, one field, one season (`season` is a first-class column; see the migration's docblock for why)
- **PlantingRecord** / **HarvestRecord**: operational logs against a crop cycle

## Responsive shell

The app shell in `resources/views/layouts/app.blade.php` works on phones. At 860px and wider the 260px sidebar and top bar are unchanged. Below 860px the sidebar becomes an off-canvas drawer opened by the labelled **Menu** button in the top bar (`aria-controls="app-sidebar"`, `aria-expanded` kept in sync). Focus moves into the drawer, Tab stays inside it, Escape or a tap on the backdrop closes it and returns focus to the button, and the page behind does not scroll while it is open. The drawer transition is switched off under `prefers-reduced-motion`.

Page markup opts in through a few classes defined in the layout: `page-pad` (page padding), `r-stack` (a grid that becomes one column on phones), `table-scroll` (wrapper that lets a wide table scroll sideways instead of widening the page) and `bell-panel` (the notification dropdown, pinned to the viewport on phones). Use them on new pages. The drawer script is plain JavaScript rather than Alpine, because the layout loads two Alpine instances (the CDN build and Livewire's bundle).

`tests/Feature/ResponsiveShellTest.php` asserts the hooks above so a regression is caught; it does not test pixels.

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

## Email via Dot.Notify

The in-app harvest notification this app already creates is also posted, server to server, to Dot.Notify
(`POST {NOTIFY_INGEST_URL}`, signed with HMAC-SHA256 over `"{timestamp}.{raw body}"` using
`NOTIFY_INGEST_SECRET`, headers `X-InfoDot-Timestamp` and `X-InfoDot-Signature`), so a company can turn
it into an email. Same trigger (a harvest record is created, after its transaction commits) and the same
recipients (every member of the harvest's team) as the in-app notification.

| Event | Sent to | `data` keys |
|---|---|---|
| `harvest.recorded` | each member of the farm's team, one event per person | `farm_name`, `field_name`, `crop`, `quantity` (two decimals, e.g. `"500.00"`), `unit`, `harvested_at` (ISO 8601), `url` |

`url` is an absolute `https` link to the harvest page in this app. The email address is only ever in
`recipient.email`; no other member's address is sent.

**What it takes for an email to go out:** nothing is sent until `NOTIFY_INGEST_URL` **and**
`NOTIFY_INGEST_SECRET` are both set, **and** the company has enabled the event in Dot.Notify (otherwise
Notify answers `no_rule` and sends nothing). A **queue worker is required** (`php artisan queue:work`):
events are queued after the harvest transaction commits, so a rolled-back harvest sends nothing, and
nothing is ever posted inside the user's request.

**Who is skipped:** Notify routes by company. For a harvest the company is the **farm's own team**
(`teams.infodot_team_uuid`), not each recipient's current team, so a harvest is never announced on behalf
of another company. A team without an `infodot_team_uuid` (not provisioned from InfoDot), or a member with
no email, is skipped, with only an info log line (event name and user id, never an address). The in-app
notification is unaffected.

**Safety:** a Notify outage or misconfiguration cannot break or slow recording a harvest. The job has a
5 second HTTP timeout, 3 tries with backoff, never throws, logs without secrets or email addresses, and a
small cache-based circuit breaker pauses calls for 60 seconds after 5 consecutive failures (jobs wait and
retry; if Notify stays down they are dropped after their third try). Retries and double dispatches share
an idempotency key, `"harvest.recorded:{harvest record id}:{sha1(recipient email)}:recorded"`, so Notify
collapses them.

## Daily metrics to Dot.Analytics

Dot.Farms publishes **one daily snapshot of aggregate numbers per company** to [Dot.Analytics](https://github.com/sakhilebhayi/Dot.Analytics), so its dashboards and briefings show real figures. Analytics only receives; this app never reads anything back from it, so an Analytics outage or a wrong setting changes nothing here (`farms:publish-metrics`, `App\Services\Analytics\MetricsCollector`, `POST /api/ingest/snapshots`).

What is sent for a company (company = a team linked to InfoDot, not a personal team):

```json
{ "platform": "farms", "company_uuid": "<the team's InfoDot uuid>", "taken_at": "2026-11-05T00:00:00Z", "period": "day", "metrics": { ... } }
```

| Key | Meaning |
|---|---|
| `active_cycles` | crop cycles in the ground: planted or growing (the dashboard's "crops in season"); planned cycles are not counted |
| `harvests_30d` | harvest records dated in the last 30 days, in any unit |
| `harvested_kg_30d` | kilograms harvested in the last 30 days: **only `kg` and `t` count** (1 t = 1000 kg); bags, crates, bunches, litres, heads and unrecognised units are never converted or added in |

No farm, field or crop names, notes, quality grades or any other row data are sent.

**Aggregates only.** Never a name, email, title, reason, note or any other row. Counts leave out archived and deleted rows, every query is scoped to the one company, and "today" and the windows are in UTC.

- **Nothing is sent until `ANALYTICS_INGEST_URL` and `ANALYTICS_INGEST_SECRET` are both set.** `ANALYTICS_INGEST_URL` is the full address ending `/api/ingest/snapshots`. `ANALYTICS_INGEST_SECRET` must equal `ANALYTICS_INGEST_SECRET_<PLATFORM KEY>` in Dot.Analytics' `.env`; `ANALYTICS_PLATFORM_KEY` defaults to `farms`.
- **The scheduler must run** (`* * * * * php artisan schedule:run` from cron; this app had no scheduled task before this one): `farms:publish-metrics` is scheduled daily at 02:10 UTC. **A queue worker is required** (`php artisan queue:work`); with `QUEUE_CONNECTION=sync` the snapshots would be sent inline by the command, which is also fine.
- Try it safely: `php artisan farms:publish-metrics --dry-run` prints each payload and sends nothing (it works with no configuration). `--company=<InfoDot team uuid>` limits a run to one company.
- Safe to repeat: `taken_at` is the start of the UTC day, and Analytics treats the same company, platform, `taken_at` and period as one snapshot, so a re-run or a retry the same day cannot double count.
- Analytics can never break or slow this app: 5 second timeout, no redirects followed, 3 tries with backoff (60 s, then 5 min), every failure caught and logged without the body or the secret, and after 5 failures in a row calls pause for 60 seconds (a cache-based circuit breaker). A snapshot that cannot be delivered is simply replaced by the next day's.
- Requests are signed with HMAC-SHA256 over `"{timestamp}.{raw body}"` (`X-InfoDot-Timestamp`, `X-InfoDot-Signature: sha256=<hex>`), made at send time so a retry carries a fresh timestamp.

## Removed records

Deleting a farm, field, crop, crop cycle, planting or harvest record is soft: the row stays, marked deleted. **Removed** in the sidebar (`/removed`) lists everything the current company has deleted, newest first, with a type filter, a search box and pagination. The list shows when each record was removed; it does not show who removed it, because nothing records that (only `deleted_at` exists).

**Restoring** (owner and admin only; editors and viewers see the list but no Restore control, and the route answers 403):

- A record cannot come back while something above it is still removed: a field needs its farm, a crop cycle its field (and its crop), a planting or harvest its crop cycle. The screen names the topmost removed ancestor ("Restore the farm Kopje Home Farm first") and the restore is refused with the same message if forced.
- Restoring a farm, field or crop cycle also brings back what was removed in the same delete: its descendants whose removal time is within 5 seconds of its own, which is how a delete cascade stamps them. Something deleted on its own earlier stays removed and is restored separately. A crop cycle whose crop has since been removed is left removed and the message says so.
- A restored field is refused if a live field on the same farm has taken its code since.
- Facts are re-derived through `CropCycleLifecycle`, not copied back: a restored cycle's planting date and planned/planted state follow its live plantings, a cycle closed as harvested with no harvest left is re-opened; a restored planting or harvest passes the same date rules as a new one (a harvest cannot predate the planting) and a harvest is refused on a planned, failed or abandoned cycle (record a planting, or re-open it, first). A restored harvest never closes the cycle and does not send the harvest notification again. Restored harvests count in the unit-family totals again (kg and t together, every other unit on its own).
- Each restore writes one row to `record_restorations` (company, user, record type and id, a name snapshot, how many records came back with it). The table is append-only: the model refuses to update or delete a row.

**Permanent deletion.** There is no button for it. The scheduler runs `php artisan farms:purge-removed` daily at 03:10 UTC; it hard-deletes records removed more than `REMOVED_RETENTION_DAYS` days ago (default 90, minimum 1; the command refuses to run with less).

- Children go with their parent: a farm, field or cycle past retention is purged only when nothing live is left anywhere beneath it, and its removed descendants are deleted with it whatever their own age. A removed crop is purged only when no crop cycle, live or removed, still names it. Live rows are never touched.
- `--dry-run` reports exactly what a real run would delete (it executes the deletes in a transaction and rolls it back) and changes nothing. Running it twice deletes nothing the second time. Each run logs one `farms.purge-removed` line with the cutoff and the counts per table.
- The scheduler must be running (`* * * * * php artisan schedule:run`); set `REMOVED_RETENTION_DAYS` in `.env` to change the window. `record_restorations` rows are kept.

## Ecosystem

**Dot.Farms** is one of the platforms in the Dot Ecosystem, connected via shared PostgreSQL and
Sanctum SSO, and via Knowledge Packs published to [Dot.Brain](https://github.com/sakhilebhayi/Dot.Brain).
See `wiki.md` for the full architecture and cross-platform relationships.

## License

MIT

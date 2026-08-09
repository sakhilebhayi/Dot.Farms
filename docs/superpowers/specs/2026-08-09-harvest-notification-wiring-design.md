# Harvest Notification Wiring — Design Spec

**Status:** Approved by user, ready for implementation planning.
**Platform:** Dot.Farms
**Date:** 2026-08-09

## Context

`Dot.Brain/platforms/dot-farms.md`'s Autonomy Classification audit (§Level 1, §Level 2,
2026-08-08) confirmed zero automation in this codebase: no `app/Console/Commands`, no
`Schedule::` entries, no `app/Jobs`. It names a specific, already-scaffolded gap as the
platform's first real Level 1 candidate: `App\Notifications\HarvestRecordedNotification` is
fully built (in-app database-channel notification, rendered by the existing `NotificationBell`
Livewire component) but its own docblock states it is "not yet wired to any automatic trigger —
dispatch manually ... until the `agriculture.harvest.recorded` event ... is actually published
anywhere."

Verified directly against the real code before proceeding:

- `app/Console/Commands`, `app/Jobs`, `app/Observers` do not exist as directories.
- `routes/console.php` contains only Laravel's stock `inspire` command.
- `grep -rn "->notify(" app` finds exactly one hit — inside `HarvestRecordedNotification`'s own
  docblock, describing the manual call site. Nothing in application code ever calls it.
- `tests/Feature/NotificationBellTest.php` already calls `$user->notify(new
  HarvestRecordedNotification($harvestRecord))` manually inside its own test setup — proving the
  notification's `toDatabase()` payload and the bell component both work end-to-end; only the
  automatic trigger is missing.
- No natural Level 2 pairing exists: `routes/web.php` has no destroy/delete routes anywhere in
  the Farm/Field/CropCycle/HarvestRecord domain — every write action is either create-only or
  update-only, none destructive or escalation-worthy. Presented to the user as an explicit choice
  (build Level 1 only / look for a different gap / skip entirely); **the user chose to build
  Level 1 only**, matching the precedent already set for Dot.Design (the only other platform this
  program where a real Level 1 gap existed without a natural Level 2).

## Goal

Wire `HarvestRecordedNotification` to fire automatically, for every member of the farm's owning
team, whenever a `HarvestRecord` is created — regardless of entry point (controller, factory,
seeder, a future API), not just the one `HarvestRecordController::store()` call site that exists
today.

## Design

### `HarvestRecordObserver`

New class `App\Observers\HarvestRecordObserver`, registered via `HarvestRecord::observe(...)` in
`App\Providers\AppServiceProvider::boot()` (currently empty — this is the natural, idiomatic
Laravel location for model-observer registration).

```php
public function created(HarvestRecord $harvestRecord): void
{
    $harvestRecord->loadMissing('cropCycle.field.farm.team');

    $team = $harvestRecord->cropCycle->field->farm->team;

    Notification::send($team->allUsers(), new HarvestRecordedNotification($harvestRecord));
}
```

- **Recipients — every team member, not just the acting user.** A harvest is farm-wide news, and
  `Team::allUsers()` (Jetstream's built-in owner + members accessor, `vendor/laravel/jetstream/
  src/Team.php:24`) is the same population `FarmPolicy`'s `belongsToTeam` check already treats as
  having visibility into a farm. `NotificationBellTest`'s existing manual-dispatch example only
  notifies the acting user — that pattern was correct for isolating the bell component in a test,
  but is not the right recipient scope for the real, automatic trigger.
- **Why an observer, not a controller-level `->notify()` call.** `HarvestRecordController::store()`
  is the only creation path today, but an observer fires on `HarvestRecord::create()` regardless
  of entry point. This matches the audit's own suggested fix ("an Eloquent observer or model
  event") and avoids the trigger silently going stale if a second creation path is added later
  (e.g. a bulk-import command) without every future author remembering to call `->notify()`
  themselves.
- **No new tables, no scheduling, no new UI.** `NotificationBell` (`app/Livewire/
  NotificationBell.php`) already reads `auth()->user()->notifications()` and renders whatever is
  there — this change only makes sure something real lands in that table.

### Docblock cleanup

`app/Notifications/HarvestRecordedNotification.php`'s class docblock currently reads:

> Not yet wired to any automatic trigger — dispatch manually via `$user->notify(new
> HarvestRecordedNotification($harvestRecord))` until the `agriculture.harvest.recorded` event
> (wiki.md §5) is actually published anywhere.

This becomes stale once the observer ships. Replace it with a note pointing at
`App\Observers\HarvestRecordObserver` as the real trigger, and that `agriculture.harvest.recorded`
(the cross-platform Knowledge Pack event, wiki.md §5) is still not published anywhere — this
change is purely in-app notification wiring, not the ecosystem event integration wiki.md
describes as future work. `NotificationBellTest`'s existing manual `->notify()` calls are
unaffected — they test the bell component and payload rendering in isolation, not the trigger.

## Out of Scope

- Publishing the `agriculture.harvest.recorded` Knowledge Pack event to Dot.Brain — that is a
  separate, larger cross-platform integration (wiki.md §5, §9 roadmap), not part of this change.
- Wiring any other notification trigger (e.g. `agriculture.moisture.threshold` — no
  `MoistureReading` model exists in this codebase; it remains wiki.md documentation only, same
  status confirmed for Dot.Dopemine's "Wellbeing observation"/"Mechanic outcome" ledgers earlier
  this program).
- Mail or broadcast notification channels — `HarvestRecordedNotification::via()` already returns
  only `['database']`; this spec does not change that.

## Testing Notes

- Creating a `HarvestRecord` (via the factory, matching every other test's convention in this
  repo) results in one `notifications` row per team member — not just the acting user — and zero
  rows for a member of a different team.
- A team with exactly one member (a personal team, `User::factory()->withPersonalTeam()->create()`
  — this repo's existing convention) still gets exactly one notification.
- The existing `HarvestRecordController::store()` flow (an authenticated HTTP request) still
  results in the same notification fan-out — an integration-level check, not just a raw model
  test, since the observer must fire through the real request path too.

### Correction found during spec self-review

`tests/Feature/NotificationBellTest.php`'s private `createHarvestRecord()` helper calls
`HarvestRecord::factory()->create(...)` — once the observer exists, that call alone will already
notify the team. Two of the test's three methods then call `$user->notify(new
HarvestRecordedNotification($harvestRecord))` *again* immediately afterward, on the assumption
that nothing else populates the `notifications` table. Post-observer, that manual call becomes a
second, redundant notification, breaking `test_unread_count_reflects_database_notifications`'s
`assertDatabaseCount('notifications', 1)` and its `assertEquals(1, ...unreadNotifications()...)`
assertion (both would see 2, not 1). The implementation task must remove the now-redundant manual
`$user->notify(...)` calls from `test_unread_count_reflects_database_notifications` and
`test_mark_all_as_read_clears_unread_count` — the observer firing during `createHarvestRecord()`
already produces the one notification these tests need to assert against. This is a required
edit to bring an existing test file's setup in line with the new automatic behavior, not a
weakening of coverage: the same assertions still run, against a notification the system now
creates for a more realistic reason (an observer, not a manually-authored `->notify()` call).
`test_dashboard_renders_notification_bell_for_authenticated_user` does not call
`createHarvestRecord()` and needs no change.

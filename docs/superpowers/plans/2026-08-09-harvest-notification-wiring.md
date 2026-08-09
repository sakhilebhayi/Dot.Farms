# Harvest Notification Wiring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `App\Notifications\HarvestRecordedNotification` fire automatically for every member
of a farm's owning team whenever a `HarvestRecord` is created, via an Eloquent model observer.

**Architecture:** A new `App\Observers\HarvestRecordObserver`, registered in
`App\Providers\AppServiceProvider::boot()`, listens for `HarvestRecord::created`. It walks the
relation chain to the owning team and sends the existing, unmodified notification to every member
via Jetstream's `Team::allUsers()`.

**Tech Stack:** Laravel 13.17, PHP (per repo's existing conventions), PHPUnit, Jetstream Teams.

## Global Constraints

- Recipients are every member of the farm's team (`Team::allUsers()`), not just the acting user —
  spec §Design.
- The observer must fire regardless of entry point (factory, seeder, controller) — it is a model
  observer, not a controller-level call.
- `HarvestRecordedNotification` itself is not modified — only its class docblock, to remove the
  now-stale "not yet wired" note.
- No new tables, no scheduling, no new UI, no mail/broadcast channels — `via()` stays
  `['database']` only.
- `tests/Feature/NotificationBellTest.php`'s `createHarvestRecord()`-based tests must have their
  now-redundant manual `$user->notify(...)` calls removed, per the spec's documented
  self-review finding — otherwise they will double-count notifications once the observer exists.

---

## Task 1: `HarvestRecordObserver` + registration + docblock + fix pre-existing test collision

**Files:**
- Create: `app/Observers/HarvestRecordObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Notifications/HarvestRecordedNotification.php` (docblock only)
- Modify: `tests/Feature/NotificationBellTest.php`
- Test: `tests/Feature/HarvestRecordObserverTest.php`

**Interfaces:**
- Consumes: `App\Models\HarvestRecord` (existing, `cropCycle()` relation), `App\Models\CropCycle`
  (existing, `field()` relation), `App\Models\Field` (existing, `farm()` relation), `App\Models\Farm`
  (existing, `team()` relation), `Laravel\Jetstream\Team::allUsers()` (framework-provided),
  `App\Notifications\HarvestRecordedNotification` (existing, unmodified constructor
  `__construct(public HarvestRecord $harvestRecord)`).
- Produces: `App\Observers\HarvestRecordObserver::created(HarvestRecord $harvestRecord): void`,
  registered against `HarvestRecord` in `AppServiceProvider::boot()`. Task 2 relies on this firing
  automatically through the real controller request path.

- [ ] **Step 1: Write the failing observer test**

Create `tests/Feature/HarvestRecordObserverTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HarvestRecordObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_harvest_record_notifies_every_team_member(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $secondMember = User::factory()->create();
        $team->users()->attach($secondMember, ['role' => 'admin']);

        $this->createHarvestRecord($team->id);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $secondMember->fresh()->unreadNotifications()->count());
    }

    public function test_a_personal_team_with_one_member_gets_exactly_one_notification(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $this->createHarvestRecord($owner->currentTeam->id);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_a_member_of_a_different_team_is_not_notified(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $otherTeamUser = User::factory()->withPersonalTeam()->create();

        $this->createHarvestRecord($owner->currentTeam->id);

        $this->assertSame(0, $otherTeamUser->fresh()->unreadNotifications()->count());
    }

    public function test_the_notification_payload_references_the_created_harvest_record(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $harvestRecord = $this->createHarvestRecord($owner->currentTeam->id);

        $notification = $owner->fresh()->notifications()->first();

        $this->assertSame('harvest_recorded', $notification->data['type']);
        $this->assertSame($harvestRecord->id, $notification->data['harvest_record_id']);
    }

    private function createHarvestRecord(int $teamId): HarvestRecord
    {
        $farm = Farm::factory()->create(['team_id' => $teamId]);
        $field = Field::factory()->create(['farm_id' => $farm->id]);
        $crop = Crop::factory()->create(['team_id' => $teamId]);
        $cropCycle = CropCycle::factory()->create([
            'field_id' => $field->id,
            'crop_id' => $crop->id,
        ]);

        return HarvestRecord::factory()->create([
            'crop_cycle_id' => $cropCycle->id,
            'quantity_harvested' => 320,
            'unit' => 'kg',
        ]);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/HarvestRecordObserverTest.php`
Expected: FAIL — `assertDatabaseCount('notifications', 2)` sees `0` (nothing dispatches yet).

- [ ] **Step 3: Write the observer**

Create `app/Observers/HarvestRecordObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\HarvestRecord;
use App\Notifications\HarvestRecordedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Fires HarvestRecordedNotification for every member of the harvest's
 * owning team, on every HarvestRecord creation regardless of entry point
 * (controller, factory, seeder, or a future API) — see
 * docs/superpowers/specs/2026-08-09-harvest-notification-wiring-design.md.
 */
class HarvestRecordObserver
{
    public function created(HarvestRecord $harvestRecord): void
    {
        $harvestRecord->loadMissing('cropCycle.field.farm.team');

        $team = $harvestRecord->cropCycle->field->farm->team;

        Notification::send($team->allUsers(), new HarvestRecordedNotification($harvestRecord));
    }
}
```

- [ ] **Step 4: Register the observer**

Replace the full contents of `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Models\HarvestRecord;
use App\Observers\HarvestRecordObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        HarvestRecord::observe(HarvestRecordObserver::class);
    }
}
```

- [ ] **Step 5: Update the notification's docblock**

In `app/Notifications/HarvestRecordedNotification.php`, replace the class docblock:

```php
/**
 * In-app (database channel only) notification for a newly recorded
 * harvest. Fired automatically for every member of the harvest's owning
 * team by App\Observers\HarvestRecordObserver, registered against
 * HarvestRecord in AppServiceProvider::boot(). The
 * `agriculture.harvest.recorded` Knowledge Pack event (wiki.md §5) is
 * still not published anywhere — this notification is purely in-app,
 * not the cross-platform ecosystem integration.
 */
```

- [ ] **Step 6: Run the observer test — expect it to still fail**

Run: `php artisan test --compact tests/Feature/HarvestRecordObserverTest.php`
Expected: still FAIL, but now on `PHPUnit\Framework\ExpectationFailedException` from a *different*
assertion than Step 2 — `assertDatabaseCount('notifications', 2)` should now pass, but the suite
run itself may still be interrupted by unrelated failures in `NotificationBellTest.php` if you run
the full directory. Run this file alone, in isolation, to confirm just this file's four tests:
Expected: 4 passed.

- [ ] **Step 7: Fix the now-redundant manual `->notify()` calls in `NotificationBellTest`**

`app/Providers/AppServiceProvider.php`'s new `boot()` means `createHarvestRecord()` inside
`tests/Feature/NotificationBellTest.php` now already sends one notification per team member by
itself. Two of that file's three tests then call `$user->notify(...)` a second time, which will
now double-count. Read the current file, then remove the now-redundant manual notify call from
each of these two tests (the third test doesn't call `createHarvestRecord()` at all and needs no
change):

In `test_unread_count_reflects_database_notifications`, remove this line (it's no longer needed —
the observer already notified `$user` when `createHarvestRecord()` ran):

```php
        $user->notify(new HarvestRecordedNotification($harvestRecord));
```

so the test body reads:

```php
    public function test_unread_count_reflects_database_notifications(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $harvestRecord = $this->createHarvestRecord($team->id);

        $this->assertDatabaseCount('notifications', 1);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSet('open', false)
            ->call('toggle')
            ->assertSet('open', true)
            ->assertSee('Harvest recorded');

        $this->assertEquals(1, $user->fresh()->unreadNotifications()->count());
    }
```

In `test_mark_all_as_read_clears_unread_count`, remove the same line so the test body reads:

```php
    public function test_mark_all_as_read_clears_unread_count(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->createHarvestRecord($team->id);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAllAsRead');

        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }
```

The `use App\Notifications\HarvestRecordedNotification;` import at the top of the file is now
unused after these two edits — remove that import line too.

- [ ] **Step 8: Run both test files together to confirm the fix**

Run: `php artisan test --compact tests/Feature/HarvestRecordObserverTest.php tests/Feature/NotificationBellTest.php`
Expected: 7 passed (4 from `HarvestRecordObserverTest`, 3 from `NotificationBellTest`).

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Observers/HarvestRecordObserver.php app/Providers/AppServiceProvider.php app/Notifications/HarvestRecordedNotification.php tests/Feature/HarvestRecordObserverTest.php tests/Feature/NotificationBellTest.php
git commit -m "feat(farms): auto-fire HarvestRecordedNotification via a model observer"
```

---

## Task 2: Integration coverage through the real controller flow + full regression

**Files:**
- Test: `tests/Feature/HarvestRecordControllerNotificationTest.php`

**Interfaces:**
- Consumes: `App\Http\Controllers\Farms\HarvestRecordController::store()` (existing, unmodified),
  `App\Observers\HarvestRecordObserver` (Task 1).

- [ ] **Step 1: Write the failing integration test**

Create `tests/Feature/HarvestRecordControllerNotificationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The observer in App\Observers\HarvestRecordObserver must fire through
 * the real HTTP request path, not just via direct model/factory creation
 * — see docs/superpowers/specs/2026-08-09-harvest-notification-wiring-design.md.
 */
class HarvestRecordControllerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_harvest_through_the_controller_notifies_the_team(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $secondMember = User::factory()->create();
        $team->users()->attach($secondMember, ['role' => 'admin']);

        $farm = Farm::factory()->create(['team_id' => $team->id]);
        $field = Field::factory()->create(['farm_id' => $farm->id]);
        $crop = Crop::factory()->create(['team_id' => $team->id]);
        $cropCycle = CropCycle::factory()->create([
            'field_id' => $field->id,
            'crop_id' => $crop->id,
        ]);

        $this->actingAs($owner)->post(
            route('crop-cycles.harvest.store', [$farm, $field, $cropCycle]),
            [
                'harvested_at' => now()->toDateString(),
                'quantity_harvested' => 500,
                'unit' => 'kg',
            ]
        )->assertRedirect();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $secondMember->fresh()->unreadNotifications()->count());
    }
}
```

- [ ] **Step 2: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/HarvestRecordControllerNotificationTest.php`
Expected: 1 passed. (The observer from Task 1 already makes this pass — this test exists purely
to lock in that the real HTTP path is covered, per the spec's Testing Notes, not to drive new
implementation.)

If it fails, re-check that `App\Providers\AppServiceProvider::boot()` from Task 1 is registered
and that the route name `crop-cycles.harvest.store` matches `routes/web.php`.

- [ ] **Step 3: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/HarvestRecordControllerNotificationTest.php
git commit -m "test(farms): cover harvest notification fan-out through the real controller path"
```

- [ ] **Step 4: Run the full test suite**

Run: `php artisan test --compact`
Expected: every pre-existing test still passes, plus the 5 new tests from this plan (4 in
`HarvestRecordObserverTest`, 1 in `HarvestRecordControllerNotificationTest`), plus
`NotificationBellTest`'s 3 tests still passing with their Task-1 edits.

- [ ] **Step 5: Confirm no leftover references to the old manual-dispatch pattern**

Run: `grep -rn "user->notify(new HarvestRecordedNotification" app tests`
Expected: no matches — Task 1 removed the only two call sites, and the notification is now only
ever constructed by the observer.

- [ ] **Step 6: Report**

Summarize the final test count and confirm this plan's scope (Task 1-2) is fully implemented
against `docs/superpowers/specs/2026-08-09-harvest-notification-wiring-design.md`. No further
commits in this step — Step 4's regression run and Step 5's grep are verification only.

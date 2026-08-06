<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Dot.Farms is Jetstream-Teams-based (see FarmPolicy, wiki.md §7). Only
 * `farms` and `crops` carry a `team_id` column directly — every other
 * agriculture table (fields, crop_cycles, planting_records,
 * harvest_records) is scoped through its parent chain by design (see the
 * docblock on the 2026_08_01_100002_create_agriculture_tables migration),
 * and is already protected consistently via FarmPolicy + Gate::authorize
 * calls in each controller rather than a duplicated team_id column. This
 * trait applies only to the two models that own the column directly.
 *
 * The goal mirrors Dot.Mines' HasTeamFilters and Dot.Finance's
 * HasUserScope: a forgotten where('team_id', ...) call in a future
 * controller can no longer leak another team's rows, because the model
 * itself never returns unscoped results while a user with a current team
 * is authenticated.
 *
 * If the user has no current team (e.g. mid-onboarding, or an API/console
 * context with no authenticated session), the scope is skipped entirely
 * rather than forced to return zero rows — there is no ambiguous
 * "current team" to filter by, and callers in that context (invitations,
 * console commands) are expected to scope explicitly themselves.
 *
 * Mass assignment still sets team_id explicitly at create time (see each
 * controller's store()); this scope only governs reads.
 */
trait HasTeamScope
{
    protected static function bootHasTeamScope(): void
    {
        static::addGlobalScope('team', function (Builder $builder): void {
            if (Auth::check() && Auth::user()->currentTeam) {
                $builder->where($builder->getModel()->getTable().'.team_id', Auth::user()->currentTeam->id);
            }
        });
    }
}

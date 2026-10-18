<?php

namespace App\Models\Concerns;

use App\Models\Team;
use App\Support\Farms\TeamChain;
use Illuminate\Database\Eloquent\Builder;

/**
 * Team isolation for the models that own a `team_id` column (farms, crops).
 * The four tables beneath them (fields, crop cycles, planting records,
 * harvest records) are protected the same way through their parent chain by
 * HasTeamChainScope. Together they mean a forgotten where('team_id', ...) in
 * a future controller cannot leak another team's rows.
 *
 * Fails closed. While a user is authenticated the scope always applies, and a
 * user with no current team (mid-onboarding, just removed from their last
 * team) sees no rows at all, not every team's rows.
 *
 * It is skipped only when nobody is authenticated: artisan commands, queued
 * jobs and scheduled tasks, which have no "current team" by nature. Those
 * callers must scope explicitly with forTeam($team). Guests never reach
 * team data because every route that touches it sits behind auth.
 *
 * Mass assignment still sets team_id explicitly at create time (see each
 * controller's store()); this scope only governs reads.
 */
trait HasTeamScope
{
    protected static function bootHasTeamScope(): void
    {
        static::addGlobalScope('team', function (Builder $builder): void {
            if (! TeamChain::hasAuthenticatedUser()) {
                return;
            }

            $builder->forTeam(TeamChain::currentTeamId());
        });
    }

    /** Explicit team filter, usable in any context (console, reports, dashboards). A null team matches nothing. */
    public function scopeForTeam(Builder $query, Team|int|null $team): Builder
    {
        $teamId = TeamChain::idOf($team);

        return $teamId === null
            ? $query->whereRaw('1 = 0')
            : $query->where($query->getModel()->getTable().'.team_id', $teamId);
    }
}

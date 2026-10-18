<?php

namespace App\Models\Concerns;

use App\Models\Team;
use App\Support\Farms\TeamChain;
use Illuminate\Database\Eloquent\Builder;

/**
 * Team isolation for models that reach their team through the parent chain
 * (field -> farm -> team). The using model implements scopeForTeam() with the
 * right subquery from TeamChain; this trait applies it as a global scope with
 * the same fail-closed rule as HasTeamScope: always while a user is
 * authenticated (no current team means no rows), skipped only in the console
 * and queue, where callers scope explicitly with forTeam($team).
 *
 * @method static Builder forTeam(Team|int|null $team)
 */
trait HasTeamChainScope
{
    protected static function bootHasTeamChainScope(): void
    {
        static::addGlobalScope('team', function (Builder $builder): void {
            if (! TeamChain::hasAuthenticatedUser()) {
                return;
            }

            $builder->forTeam(TeamChain::currentTeamId());
        });
    }
}

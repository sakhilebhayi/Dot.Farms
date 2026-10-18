<?php

namespace App\Support\Farms;

use App\Models\Team;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Resolves "which rows belong to this team" for the models that have no
 * team_id of their own. Only farms and crops carry team_id; fields, crop
 * cycles, planting records and harvest records reach their team through
 * field -> farm -> team. The subqueries here walk that chain with plain
 * table queries (no model scopes, so no recursion) and refuse to pass through
 * a soft-deleted farm, field or cycle: a record under a deleted parent is
 * gone for every team-scoped query, even if a cascade somehow missed it.
 *
 * The Removed screen is the one caller that must see through deleted parents
 * (a removed field under a removed farm is exactly what it lists), so each
 * method takes $withTrashed. That drops only the deleted_at tests; the team
 * condition on farms.team_id stays, so it can widen what a team sees of its
 * own removed rows but never reach another team's.
 */
final class TeamChain
{
    public static function idOf(Team|int|null $team): ?int
    {
        return $team instanceof Team ? $team->id : $team;
    }

    /** Whether the current execution has an authenticated user (HTTP/Livewire), as opposed to console, queue or guest. */
    public static function hasAuthenticatedUser(): bool
    {
        return Auth::check();
    }

    public static function currentTeamId(): ?int
    {
        return Auth::user()?->currentTeam?->id;
    }

    public static function farmIds(int $teamId, bool $withTrashed = false): Builder
    {
        return DB::table('farms')
            ->select('farms.id')
            ->where('farms.team_id', $teamId)
            ->unless($withTrashed, fn (Builder $q) => $q->whereNull('farms.deleted_at'));
    }

    public static function fieldIds(int $teamId, bool $withTrashed = false): Builder
    {
        return DB::table('fields')
            ->join('farms', 'farms.id', '=', 'fields.farm_id')
            ->select('fields.id')
            ->where('farms.team_id', $teamId)
            ->unless($withTrashed, fn (Builder $q) => $q->whereNull('farms.deleted_at')->whereNull('fields.deleted_at'));
    }

    public static function cycleIds(int $teamId, bool $withTrashed = false): Builder
    {
        return DB::table('crop_cycles')
            ->join('fields', 'fields.id', '=', 'crop_cycles.field_id')
            ->join('farms', 'farms.id', '=', 'fields.farm_id')
            ->select('crop_cycles.id')
            ->where('farms.team_id', $teamId)
            ->unless($withTrashed, fn (Builder $q) => $q->whereNull('farms.deleted_at')->whereNull('fields.deleted_at')->whereNull('crop_cycles.deleted_at'));
    }
}

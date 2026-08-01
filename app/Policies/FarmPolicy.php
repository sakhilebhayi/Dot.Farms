<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Enforces the tenant boundary described in wiki.md §7: a user may only
 * view or manage a Farm (and, by extension, its fields, crop cycles,
 * planting records, and harvest records) if they belong to the Farm's
 * owning team. Modeled directly on Dot.Billing's BillingInvoicePolicy,
 * which was added there specifically to close a cross-team access gap —
 * applying the same check here from the start rather than adding it
 * after the fact.
 */
class FarmPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Farm $farm): bool
    {
        return $user->belongsToTeam($farm->team);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Farm $farm): bool
    {
        return $user->belongsToTeam($farm->team);
    }

    public function delete(User $user, Farm $farm): bool
    {
        return $user->belongsToTeam($farm->team);
    }
}

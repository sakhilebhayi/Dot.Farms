<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRoles;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Enforces the tenant boundary described in wiki.md §7: a user may only view
 * a Farm (and, by extension, its fields, crop cycles, planting records, and
 * harvest records) if they belong to the Farm's owning team, and may only
 * change the farm structure as team owner or admin. See ChecksFarmRoles for
 * the full role table. Modeled on Dot.Billing's BillingInvoicePolicy.
 */
class FarmPolicy
{
    use ChecksFarmRoles;
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Farm $farm): bool
    {
        return $this->isMember($user, $farm->team);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user, $this->currentTeam($user));
    }

    public function update(User $user, Farm $farm): bool
    {
        return $this->canManage($user, $farm->team);
    }

    public function delete(User $user, Farm $farm): bool
    {
        return $this->canManage($user, $farm->team);
    }

    /** Bringing a removed farm back is a management act, like removing it. */
    public function restore(User $user, Farm $farm): bool
    {
        return $this->canManage($user, $farm->team);
    }
}

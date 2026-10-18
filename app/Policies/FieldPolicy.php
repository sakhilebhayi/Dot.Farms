<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\Field;
use App\Models\User;
use App\Policies\Concerns\ChecksFarmRoles;

/** Fields are farm structure: owner/admin write, any member reads. See ChecksFarmRoles. */
class FieldPolicy
{
    use ChecksFarmRoles;

    public function view(User $user, Field $field): bool
    {
        return $this->isMember($user, $this->teamOfField($field));
    }

    public function create(User $user, Farm $farm): bool
    {
        return $this->canManage($user, $farm->team);
    }

    public function update(User $user, Field $field): bool
    {
        return $this->canManage($user, $this->teamOfField($field));
    }

    public function delete(User $user, Field $field): bool
    {
        return $this->canManage($user, $this->teamOfField($field));
    }
}

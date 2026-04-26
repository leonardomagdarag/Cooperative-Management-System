<?php

namespace App\Policies;

use App\Models\PersonalData;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('members.view');
    }

    public function view(User $user, PersonalData $personalData): bool
    {
        return $user->can('members.view')
            || (int) $user->personal_data_id === (int) $personalData->id;
    }

    public function create(User $user): bool
    {
        return $user->can('members.create');
    }

    public function update(User $user, PersonalData $personalData): bool
    {
        return $user->can('members.update')
            || (int) $user->personal_data_id === (int) $personalData->id;
    }

    public function delete(User $user): bool
    {
        return $user->can('members.delete');
    }
}

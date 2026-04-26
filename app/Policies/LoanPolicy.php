<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('loans.view');
    }

    public function view(User $user, Loan $loan): bool
    {
        return $user->can('loans.view')
            || $loan->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('loans.create');
    }

    public function update(User $user, Loan $loan): bool
    {
        return $user->can('loans.update')
            || ($user->can('loans.create') && $loan->user_id === $user->id);
    }

    public function approve(User $user): bool
    {
        return $user->can('loans.approve');
    }

    public function reject(User $user): bool
    {
        return $user->can('loans.reject');
    }

    public function delete(User $user): bool
    {
        return $user->can('loans.update');
    }
}

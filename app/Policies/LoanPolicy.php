<?php

namespace App\Policies;

use App\Models\Loan;
use App\Support\PermissionCatalog;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOfficerUser($user) && $user->can('loans.view');
    }

    public function view(User $user, Loan $loan): bool
    {
        return ($this->isOfficerUser($user) && $user->can('loans.view'))
            || $loan->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('loans.create');
    }

    public function encode(User $user): bool
    {
        return $user->hasRole('admin') && $user->can('loans.encode');
    }

    public function update(User $user, Loan $loan): bool
    {
        return ($this->isOfficerUser($user) && $user->can('loans.update'))
            || ($user->can('loans.create') && $loan->user_id === $user->id);
    }

    public function approve(User $user): bool
    {
        return $this->isOfficerUser($user) && $user->can('loans.approve');
    }

    public function uploadDocument(User $user, Loan $loan): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'accountant']);
    }

    public function reject(User $user): bool
    {
        return $this->isOfficerUser($user) && $user->can('loans.reject');
    }

    /**
     * Officer-level review action (start review, schedule interview, endorse).
     * Excludes final approval — use finalApprove() for that.
     */
    public function review(User $user): bool
    {
        return $this->isOfficerUser($user) && $user->can('loans.update');
    }

    /**
     * Final approval is restricted to admin and manager (chairman) only.
     * This is a hard role gate, independent of permissions, to prevent escalation.
     */
    public function finalApprove(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user): bool
    {
        return $this->isOfficerUser($user) && $user->can('loans.update');
    }

    private function isOfficerUser(User $user): bool
    {
        return (bool) (($user->is_officer ?? false)
            || $user->hasRole('admin')
            || $user->hasAnyRole(PermissionCatalog::officerRoles()));
    }
}

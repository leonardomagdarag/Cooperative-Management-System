<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;
use App\Support\BranchScope;
use App\Support\PermissionCatalog;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOfficerUser($user) && $user->can('loans.view');
    }

    public function view(User $user, Loan $loan): bool
    {
        return BranchScope::canAccessBranch($user, $loan->branch_id)
            && (($this->isOfficerUser($user) && $user->can('loans.view'))
                || $loan->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('loans.create');
    }

    public function encode(User $user): bool
    {
        return $this->isOfficerUser($user) && $user->can('loans.encode');
    }

    public function update(User $user, Loan $loan): bool
    {
        return BranchScope::canAccessBranch($user, $loan->branch_id)
            && (($this->isOfficerUser($user) && ($user->can('loans.update') || $user->can('loans.encode')))
                || ($user->can('loans.create') && $loan->user_id === $user->id));
    }

    public function approve(User $user): bool
    {
        return $this->isOfficerUser($user) && $user->can('loans.approve');
    }

    public function disburse(User $user, Loan $loan): bool
    {
        return BranchScope::canAccessBranch($user, $loan->branch_id)
            && $this->isOfficerUser($user)
            && $user->can('loans.disburse');
    }

    public function uploadDocument(User $user, Loan $loan): bool
    {
        return BranchScope::canAccessBranch($user, $loan->branch_id)
            && (($this->isOfficerUser($user) && $user->can('loans.update'))
                || $loan->user_id === $user->id);
    }

    public function reject(User $user): bool
    {
        return $this->isOfficerUser($user) && $user->can('loans.reject');
    }

    /**
     * Officer-level review action (start review, schedule interview, endorse).
     * Excludes final approval — use finalApprove() for that.
     */
    public function review(User $user, Loan $loan): bool
    {
        return BranchScope::canAccessBranch($user, $loan->branch_id)
            && $this->isOfficerUser($user)
            && $user->can('loans.update');
    }

    /**
     * Final approval is separated from the applicant and encoder. A chairman
     * may approve their own endorsement; their endorsement action completes
     * both steps in one request.
     */
    public function finalApprove(User $user, Loan $loan): bool
    {
        if (! BranchScope::canAccessBranch($user, $loan->branch_id) || ! $user->can('loans.approve')) {
            return false;
        }

        if ((int) $loan->user_id === (int) $user->id
            || (int) $loan->encoded_by === (int) $user->id
            || ((int) $loan->reviewed_by === (int) $user->id && ! $user->hasRole('chairman'))) {
            return false;
        }

        return true;
    }

    public function close(User $user, Loan $loan): bool
    {
        return BranchScope::canAccessBranch($user, $loan->branch_id)
            && $user->hasAnyRole(['chairman', 'manager'])
            && $user->can('loans.update');
    }

    public function delete(User $user, Loan $loan): bool
    {
        return BranchScope::canAccessBranch($user, $loan->branch_id)
            && $this->isOfficerUser($user)
            && $user->can('loans.update');
    }

    private function isOfficerUser(User $user): bool
    {
        return (bool) (($user->is_officer ?? false)
            || $user->hasAnyRole(array_merge(PermissionCatalog::officerRoles(), ['admin'])));
    }
}

<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BranchScope
{
    /** Whether the viewer is exempt from branch scoping. */
    public static function isUnrestricted(?User $viewer): bool
    {
        return $viewer !== null && $viewer->can('branches.bypass');
    }

    public static function canAccessBranch(?User $viewer, mixed $branchId): bool
    {
        return self::isUnrestricted($viewer)
            || ($viewer?->branch_id !== null
                && $branchId !== null
                && (int) $viewer->branch_id === (int) $branchId);
    }

    /**
     * Resolve the effective branch filter for a request. Restricted viewers
     * without bypass are always locked to their own branch.
     */
    public static function resolveBranchId(?User $viewer, mixed $requestedBranchId = null): ?int
    {
        if (self::isUnrestricted($viewer)) {
            $requested = (int) $requestedBranchId;

            return $requested > 0 ? $requested : null;
        }

        abort_if($viewer === null || $viewer->branch_id === null, 403, 'Your account is not assigned to a branch.');

        return $viewer->branch_id;
    }

    public static function applyToUsers(Builder $query, ?User $viewer, mixed $requestedBranchId = null): Builder
    {
        if (self::isUnrestricted($viewer)) {
            if ($requestedBranchId = (int) $requestedBranchId) {
                $query->where('users.branch_id', $requestedBranchId);
            }

            return $query;
        }

        return $viewer === null || $viewer->branch_id === null
            ? $query->whereRaw('1 = 0')
            : $query->where('users.branch_id', $viewer->branch_id);
    }

    public static function applyToTransactions(Builder $query, ?User $viewer, mixed $requestedBranchId = null): Builder
    {
        if (self::isUnrestricted($viewer)) {
            if ($requestedBranchId = (int) $requestedBranchId) {
                $query->where('transactions.branch_id', $requestedBranchId);
            }

            return $query;
        }

        return $viewer === null || $viewer->branch_id === null
            ? $query->whereRaw('1 = 0')
            : $query->where('transactions.branch_id', $viewer->branch_id);
    }

    public static function applyToLoans(Builder $query, ?User $viewer, mixed $requestedBranchId = null): Builder
    {
        if (self::isUnrestricted($viewer)) {
            if ($requestedBranchId = (int) $requestedBranchId) {
                $query->where('loans.branch_id', $requestedBranchId);
            }

            return $query;
        }

        return $viewer === null || $viewer->branch_id === null
            ? $query->whereRaw('1 = 0')
            : $query->where('loans.branch_id', $viewer->branch_id);
    }

    public static function applyToExpenses(Builder $query, ?User $viewer, mixed $requestedBranchId = null): Builder
    {
        if (self::isUnrestricted($viewer)) {
            if ($requestedBranchId = (int) $requestedBranchId) {
                $query->where('expenses.branch_id', $requestedBranchId);
            }

            return $query;
        }

        return $viewer === null || $viewer->branch_id === null
            ? $query->whereRaw('1 = 0')
            : $query->where('expenses.branch_id', $viewer->branch_id);
    }
}

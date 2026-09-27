<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BranchScope
{
    /**
     * Whether the viewer is exempt from branch scoping. Admins, developers,
     * users granted the branches.bypass permission, and users without an
     * assigned branch see data across all branches.
     */
    public static function isUnrestricted(?User $viewer): bool
    {
        if (! $viewer) {
            return true;
        }

        if ($viewer->hasRole('admin') || $viewer->hasRole('developer')) {
            return true;
        }

        if ($viewer->can('branches.bypass')) {
            return true;
        }

        return $viewer->branch_id === null;
    }

    /**
     * Resolve the effective branch filter for a request. Restricted viewers
     * (branch-assigned non-admins) are always locked to their own branch.
     */
    public static function resolveBranchId(?User $viewer, mixed $requestedBranchId = null): ?int
    {
        if (self::isUnrestricted($viewer)) {
            $requested = (int) $requestedBranchId;

            return $requested > 0 ? $requested : null;
        }

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

        return $query->where('users.branch_id', $viewer->branch_id);
    }

    public static function applyToTransactions(Builder $query, ?User $viewer, mixed $requestedBranchId = null): Builder
    {
        if (self::isUnrestricted($viewer)) {
            if ($requestedBranchId = (int) $requestedBranchId) {
                $query->where('transactions.branch_id', $requestedBranchId);
            }

            return $query;
        }

        return $query->where('transactions.branch_id', $viewer->branch_id);
    }

    public static function applyToLoans(Builder $query, ?User $viewer, mixed $requestedBranchId = null): Builder
    {
        if (self::isUnrestricted($viewer)) {
            if ($requestedBranchId = (int) $requestedBranchId) {
                $query->where('loans.branch_id', $requestedBranchId);
            }

            return $query;
        }

        return $query->where('loans.branch_id', $viewer->branch_id);
    }

    public static function applyToExpenses(Builder $query, ?User $viewer, mixed $requestedBranchId = null): Builder
    {
        if (self::isUnrestricted($viewer)) {
            if ($requestedBranchId = (int) $requestedBranchId) {
                $query->where('expenses.branch_id', $requestedBranchId);
            }

            return $query;
        }

        return $query->where('expenses.branch_id', $viewer->branch_id);
    }
}

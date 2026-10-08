<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BranchScope
{
    /**
     * Whether the viewer is exempt from branch scoping. Developers and
     * managers with the bypass permission are global. Officers assigned to
     * the main branch are also global; other assigned officers stay scoped
     * to their branch, even if an old role preset still grants bypass.
     */
    public static function isUnrestricted(?User $viewer): bool
    {
        if (! $viewer) {
            return true;
        }

        if ($viewer->hasRole('developer')) {
            return true;
        }

        $isManager = $viewer->hasAnyRole(['admin', 'chairman', 'manager']);
        if ($isManager && $viewer->can('branches.bypass')) {
            return true;
        }

        $isOfficer = (bool) $viewer->is_officer
            || $viewer->hasAnyRole(array_merge(PermissionCatalog::officerRoles(), ['admin']));

        if ($isOfficer) {
            return $viewer->branch_id === null || (bool) $viewer->branch?->isMain();
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

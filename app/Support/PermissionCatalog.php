<?php

namespace App\Support;

/**
 * Single source of truth for permission groups, labels, and role presets.
 * All Livewire components and views should import from here instead of
 * duplicating the arrays inline.
 */
class PermissionCatalog
{
    /**
     * Returns all permission groups.  Keys are human-readable group names,
     * values are arrays of Spatie permission strings.
     */
    public static function groups(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view',
            ],
            'Members' => [
                'members.view',
                'members.create',
                'members.update',
                'members.delete',
                'members.view-full-details',
            ],
            'Services' => [
                'services.view',
                'services.create',
                'services.update',
            ],
            'Loans' => [
                'loans.view',
                'loans.create',
                'loans.update',
                'loans.approve',
                'loans.reject',
                'loans.disburse',
            ],
            'Transactions' => [
                'transactions.view',
                'transactions.create',
                'transactions.update',
                'transactions.void',
                'transactions.export',
            ],
            'Deposits & Withdrawals' => [
                'deposits.view',
                'deposits.create',
                'deposits.approve',
                'deposits.reject',
                'withdrawals.view',
                'withdrawals.create',
                'withdrawals.approve',
                'withdrawals.reject',
            ],
            'Reports' => [
                'reports.view',
                'reports.export',
                'reports.financial',
                'reports.audit',
            ],
            'Settings & Logs' => [
                'settings.view',
                'settings.update',
                'logs.view',
                'logs.export',
            ],
            'Announcements' => [
                'announcements.view',
                'announcements.create',
                'announcements.update',
                'announcements.delete',
            ],
            'Staff Management' => [
                'officers.create',
                'officers.update',
                'officers.delete',
                'officers.manage-permissions',
            ],
            'Notifications & Profile' => [
                'notifications.view',
                'notifications.manage',
                'profile.view',
                'profile.update',
            ],
        ];
    }

    /**
     * Returns a flat list of all permission strings.
     */
    public static function all(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    /**
     * Human-readable label for a permission string.
     */
    public static function formatLabel(string $permission): string
    {
        $parts = explode('.', $permission);
        if (count($parts) === 2) {
            $resource = ucfirst($parts[0]);
            $action = match ($parts[1]) {
                'view'               => 'View',
                'create'             => 'Create',
                'update'             => 'Edit',
                'delete'             => 'Delete',
                'approve'            => 'Approve',
                'reject'             => 'Reject',
                'disburse'           => 'Disburse',
                'void'               => 'Void',
                'export'             => 'Export',
                'manage-permissions' => 'Manage Permissions',
                'view-full-details'  => 'View Full Details',
                'financial'          => 'Financial Reports',
                'audit'              => 'Audit Reports',
                'manage'             => 'Manage',
                default              => ucfirst($parts[1]),
            };
            return "{$resource}: {$action}";
        }

        return ucfirst(str_replace(['.', '-'], ' ', $permission));
    }

    /**
     * Human-readable display names for the four officer roles.
     */
    public static function roleDisplayNames(): array
    {
        return [
            'manager'   => 'Manager',
            'secretary' => 'Secretary',
            'loan'      => 'Loan Officer',
            'treasurer' => 'Treasurer',
        ];
    }

    /**
     * Short descriptions of each officer role's responsibilities.
     */
    public static function roleDescriptions(): array
    {
        return [
            'manager'   => 'Oversees all cooperative operations. Has broad access to members, loans, transactions, reports, announcements, and staff management.',
            'secretary' => 'Handles records and communications. Manages member registrations, creates announcements, and exports basic reports.',
            'loan'      => 'Focuses on loan processing. Creates and reviews loan applications and manages loan products and services.',
            'treasurer' => 'Manages cooperative finances. Handles transactions, deposits, withdrawals, and financial reports.',
        ];
    }

    /**
     * Default permissions per officer role, used as seeder baselines and
     * as convenient presets in the role-permission manager.
     */
    public static function rolePresets(): array
    {
        return [
            'manager' => [
                'dashboard.view',
                'members.view',
                'members.create',
                'members.update',
                'members.delete',
                'members.view-full-details',
                'services.view',
                'services.create',
                'services.update',
                'loans.view',
                'loans.create',
                'loans.update',
                'loans.approve',
                'loans.reject',
                'loans.disburse',
                'transactions.view',
                'transactions.create',
                'transactions.update',
                'transactions.export',
                'deposits.view',
                'deposits.create',
                'deposits.approve',
                'withdrawals.view',
                'withdrawals.create',
                'withdrawals.approve',
                'reports.view',
                'reports.export',
                'announcements.view',
                'announcements.create',
                'announcements.update',
                'announcements.delete',
                'settings.view',
                'settings.update',
                'officers.create',
                'officers.update',
                'officers.delete',
                'officers.manage-permissions',
                'logs.view',
                'logs.export',
                'notifications.view',
                'notifications.manage',
                'profile.view',
                'profile.update',
            ],
            'treasurer' => [
                'dashboard.view',
                'members.view',
                'services.view',
                'services.create',
                'services.update',
                'loans.view',
                'transactions.view',
                'transactions.create',
                'transactions.update',
                'transactions.export',
                'deposits.view',
                'deposits.create',
                'deposits.approve',
                'deposits.reject',
                'withdrawals.view',
                'withdrawals.create',
                'withdrawals.approve',
                'withdrawals.reject',
                'reports.view',
                'reports.export',
                'reports.financial',
                'announcements.view',
                'settings.view',
                'notifications.view',
                'profile.view',
                'profile.update',
            ],
            'loan' => [
                'dashboard.view',
                'members.view',
                'members.view-full-details',
                'services.view',
                'services.create',
                'services.update',
                'loans.view',
                'loans.create',
                'loans.update',
                'loans.reject',
                'transactions.view',
                'reports.view',
                'reports.export',
                'announcements.view',
                'settings.view',
                'notifications.view',
                'profile.view',
                'profile.update',
            ],
            'secretary' => [
                'dashboard.view',
                'members.view',
                'members.create',
                'members.update',
                'services.view',
                'loans.view',
                'transactions.view',
                'transactions.export',
                'reports.view',
                'reports.export',
                'announcements.view',
                'announcements.create',
                'announcements.update',
                'settings.view',
                'notifications.view',
                'profile.view',
                'profile.update',
            ],
        ];
    }
}

<?php

namespace App\Support;

class PermissionCatalog
{
    public static function officerRoles(): array
    {
        return ['manager', 'accountant', 'loan', 'secretary'];
    }

    public static function groups(): array
    {
        return [
            'Members' => [
                'members.view',
                'members.create',
                'members.update',
                'members.delete',
                'members.approve',
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
                'loans.encode',
                'loans.update',
                'loans.approve',
                'loans.reject',
                'loans.disburse',
            ],
            'Transactions' => [
                'transactions.view',
                'transactions.view-all',
                'transactions.create',
                'transactions.update',
                'transactions.approve',
                'transactions.void',
                'transactions.export',
            ],
            'Deposits' => [
                'deposits.view',
                'deposits.create',
                'deposits.approve',
                'deposits.reject',
            ],
            'Withdrawals' => [
                'withdrawals.view',
                'withdrawals.create',
                'withdrawals.approve',
                'withdrawals.reject',
            ],
            'Announcements' => [
                'announcements.view',
                'announcements.create',
                'announcements.update',
                'announcements.delete',
            ],
            'Reports' => [
                'reports.view',
                'reports.export',
                'reports.financial',
                'reports.audit',
            ],
            'Officer Management' => [
                'officers.view',
                'officers.create',
                'officers.update',
                'officers.delete',
                'officers.manage-permissions',
            ],
            'Branches' => [
                'branches.view',
                'branches.create',
                'branches.update',
                'branches.delete',
            ],
            'Logs' => [
                'logs.view',
                'logs.export',
            ],
            'Settings' => [
                'settings.view',
                'settings.update',
            ],
            'Backups' => [
                'backups.view',
                'backups.manage',
                'backups.restore',
            ],
        ];
    }

    public static function roleDisplayNames(): array
    {
        return [
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'accountant' => 'Accountant',
            'loan' => 'Loan Officer',
            'secretary' => 'Secretary',
        ];
    }

    public static function roleDescriptions(): array
    {
        return [
            'admin' => 'Full system access. Can manage all settings, roles, permissions, and officer accounts.',
            'manager' => 'Broad operational oversight across officers, members, services, and reporting.',
            'accountant' => 'Transaction handling, service processing, member onboarding, and financial reporting support.',
            'loan' => 'Loan processing, service transactions, and member loan support.',
            'secretary' => 'Handles records and communications. Manages member registrations, creates announcements, and exports basic reports.',
        ];
    }

    public static function rolePresets(): array
    {
        return [
            'manager' => [
                'dashboard.view',
                'members.view',
                'members.create',
                'members.update',
                'members.delete',
                'members.approve',
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
                'transactions.view-all',
                'transactions.create',
                'transactions.update',
                'transactions.approve',
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
                'announcements.create',
                'announcements.update',
                'announcements.delete',
                'settings.view',
                'settings.update',
                'officers.create',
                'officers.update',
                'officers.delete',
                'notifications.view',
                'profile.view',
                'profile.update',
            ],
            'accountant' => [
                'dashboard.view',
                'members.view',
                'members.create',
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
                'transactions.view-all',
                'transactions.create',
                'transactions.update',
                'transactions.export',
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
                'loans.approve',
                'loans.reject',
                'loans.disburse',
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
                'members.approve',
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

    public static function allPermissions(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::groups()))));
    }

    public static function formatLabel(string $permission): string
    {
        [$resource, $action] = array_pad(explode('.', $permission, 2), 2, null);

        if ($action === null) {
            return ucwords(str_replace(['-', '_'], ' ', $permission));
        }

        return sprintf(
            '%s %s',
            ucwords(str_replace(['-', '_'], ' ', $action)),
            ucwords(str_replace(['-', '_'], ' ', $resource))
        );
    }
}

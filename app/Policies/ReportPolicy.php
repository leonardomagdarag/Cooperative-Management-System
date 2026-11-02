<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        // This policy defines reports.view itself; can() would re-enter the same gate.
        return $user->checkPermissionTo('reports.view');
    }

    public function export(User $user): bool
    {
        return $user->checkPermissionTo('reports.view') && $user->checkPermissionTo('reports.export');
    }
}

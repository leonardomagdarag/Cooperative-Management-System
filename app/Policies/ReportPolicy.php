<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('reports.view');
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionTo('reports.export');
    }
}

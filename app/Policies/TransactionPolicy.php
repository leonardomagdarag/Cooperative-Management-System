<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use App\Support\BranchScope;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('transactions.view');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return BranchScope::canAccessBranch($user, $transaction->branch_id)
            && ($user->can('transactions.view-all')
                || ($user->can('transactions.view')
                    && (int) optional($transaction->account)->user_id === (int) $user->id));
    }

    public function create(User $user): bool
    {
        return $user->can('transactions.create');
    }

    public function update(User $user): bool
    {
        return $user->can('transactions.update');
    }

    public function export(User $user): bool
    {
        return $user->can('transactions.export');
    }
}

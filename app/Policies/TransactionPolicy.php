<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('transactions.view');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        if ($user->can('transactions.view') && ! $user->hasRole('member')) {
            return true;
        }

        return $user->can('transactions.view')
            && (int) optional($transaction->account)->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('transactions.create');
    }

    public function update(User $user): bool
    {
        return $user->can('transactions.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('transactions.delete');
    }

    public function export(User $user): bool
    {
        return $user->can('transactions.export');
    }
}

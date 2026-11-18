<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class LoanCoMakers
{
    public const RULES = [
        'co_makers' => 'nullable|array|list|max:20',
        'co_makers.*' => 'required|integer|min:1',
    ];

    public static function eligibleMembers(User $actor, int $borrowerId): Builder
    {
        return BranchScope::applyToUsers(
            User::query()->memberUsers()->where('account_status', 'active')->whereKeyNot($borrowerId),
            $actor,
        );
    }

    /** @return list<int> */
    public static function validate(array $ids, User $actor, int $borrowerId, int $requiredCount): array
    {
        $ids = array_map('intval', $ids);

        if (in_array($borrowerId, $ids, true) || count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['co_makers' => 'Select distinct co-makers other than the borrower.']);
        }

        if ($requiredCount > 0 && count($ids) !== $requiredCount) {
            throw ValidationException::withMessages(['co_makers' => "This product requires exactly {$requiredCount} co-maker(s)."]);
        }

        if ($ids !== [] && self::eligibleMembers($actor, $borrowerId)->whereKey($ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['co_makers' => 'Select active cooperative members you can access.']);
        }

        return $ids;
    }
}

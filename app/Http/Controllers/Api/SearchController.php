<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BranchScope;
use App\Support\SearchFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class SearchController extends Controller
{
    private const PAGE_CATALOG = [
        ['label' => 'Dashboard', 'keywords' => 'home overview', 'url' => '/dashboard', 'icon' => 'house', 'permission' => null, 'role' => null],
        ['label' => 'My Profile', 'keywords' => 'account personal info', 'url' => '/dashboard/profile', 'icon' => 'user', 'permission' => null, 'role' => null],
        ['label' => 'Notifications', 'keywords' => 'alerts inbox', 'url' => '/dashboard/notifications', 'icon' => 'bell', 'permission' => null, 'role' => null],
        ['label' => 'Settings', 'keywords' => 'preferences security password', 'url' => '/dashboard/settings', 'icon' => 'gear', 'permission' => null, 'role' => null],
        ['label' => 'Messages', 'keywords' => 'chat inbox', 'url' => '/dashboard/messages', 'icon' => 'envelope', 'permission' => null, 'role' => null],
        ['label' => 'Tickets', 'keywords' => 'support helpdesk', 'url' => '/dashboard/tickets', 'icon' => 'life-ring', 'permission' => null, 'role' => null],
        ['label' => 'My Loans', 'keywords' => 'borrow loan payments', 'url' => '/dashboard/member-loans', 'icon' => 'file-invoice-dollar', 'permission' => null, 'role' => 'member'],
        ['label' => 'My Transactions', 'keywords' => 'history records', 'url' => '/dashboard/history', 'icon' => 'clock-rotate-left', 'permission' => null, 'role' => 'member'],
        ['label' => 'Members', 'keywords' => 'directory roster', 'url' => '/dashboard/members', 'icon' => 'users', 'permission' => 'members.view', 'role' => null],
        ['label' => 'Loans', 'keywords' => 'borrow loan management', 'url' => '/dashboard/loans', 'icon' => 'file-invoice-dollar', 'permission' => 'loans.view', 'role' => null],
        ['label' => 'Ledger', 'keywords' => 'transactions records', 'url' => '/dashboard/ledger', 'icon' => 'book', 'permission' => 'transactions.view', 'role' => null],
        ['label' => 'Expenses', 'keywords' => 'cost spending', 'url' => '/dashboard/expenses', 'icon' => 'receipt', 'permission' => 'transactions.view', 'role' => null],
        ['label' => 'Services', 'keywords' => 'deposit withdrawal loans', 'url' => '/dashboard/services', 'icon' => 'hand-holding-dollar', 'permission' => 'services.view', 'role' => null],
        ['label' => 'Announcements', 'keywords' => 'news updates bulletin', 'url' => '/dashboard/announcements', 'icon' => 'bullhorn', 'permission' => 'announcements.view', 'role' => null],
        ['label' => 'Reports', 'keywords' => 'financial statements export', 'url' => '/dashboard/reports', 'icon' => 'chart-pie', 'permission' => 'reports.view', 'role' => null],
        ['label' => 'Officers', 'keywords' => 'staff employees', 'url' => '/dashboard/officers', 'icon' => 'user-tie', 'permission' => 'officers.view', 'role' => null],
        ['label' => 'Logs', 'keywords' => 'audit activity trail', 'url' => '/dashboard/logs', 'icon' => 'list-check', 'permission' => null, 'role' => 'admin'],
        ['label' => 'Role Permissions', 'keywords' => 'roles access control', 'url' => '/dashboard/role-permissions', 'icon' => 'shield-halved', 'permission' => null, 'role' => 'admin'],
        ['label' => 'Loan Products', 'keywords' => 'product catalog loan types', 'url' => '/dashboard/loan-products', 'icon' => 'tags', 'permission' => 'settings.update', 'role' => null],
        ['label' => 'Public Content', 'keywords' => 'website pages banners', 'url' => '/dashboard/public-content', 'icon' => 'globe', 'permission' => 'settings.update', 'role' => null],
    ];

    public function global(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());

        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }

        $user = $request->user();
        $needle = '%'.SearchFilter::escapeLike($q).'%';
        $isAdmin = $user->hasRole('admin');
        $isMember = $user->hasRole('member');
        $canViewAll = $user->is_officer || $user->hasRole('admin');
        $can = fn (string $permission): bool => $user->can($permission);

        $groups = [];

        $this->appendPages($groups, $q, $isAdmin, $isMember, $can);

        if ($can('members.view')) {
            $this->appendMembers($groups, $needle, $user);
        }

        if ($can('officers.view')) {
            $this->appendOfficers($groups, $needle, $user);
        }

        if ($canViewAll && $can('loans.view')) {
            $this->appendLoans($groups, $needle, '/dashboard/loans', null, $user);
        } elseif ($can('loans.view')) {
            $this->appendLoans($groups, $needle, '/dashboard/member-loans', $user->id, $user);
        }

        if ($canViewAll && $can('transactions.view')) {
            $this->appendLedger($groups, $needle, $user);
            $this->appendExpenses($groups, $needle, $user);
        } elseif ($can('transactions.view')) {
            $this->appendHistory($groups, $needle, $user);
        }

        if ($can('announcements.view')) {
            $this->appendAnnouncements($groups, $needle);
        }

        if ($can('settings.update')) {
            $this->appendLoanProducts($groups, $needle);
        }

        if ($isAdmin) {
            $this->appendRoles($groups, $needle);
            $this->appendLogs($groups, $needle);
        }

        $groupList = collect($groups)
            ->map(fn (array $items, string $label) => ['label' => $label, 'items' => $items])
            ->values()
            ->all();

        return response()->json(['groups' => $groupList]);
    }

    private function appendPages(array &$groups, string $q, bool $isAdmin, bool $isMember, \Closure $can): void
    {
        $pages = collect(self::PAGE_CATALOG)
            ->filter(fn (array $page) => $page['role'] === null || ($page['role'] === 'admin' && $isAdmin) || ($page['role'] === 'member' && $isMember))
            ->filter(fn (array $page) => $page['permission'] === null || $can($page['permission']))
            ->filter(function (array $page) use ($q) {
                $haystack = strtolower($page['label'].' '.($page['keywords'] ?? ''));

                return str_contains($haystack, strtolower($q));
            })
            ->map(fn (array $page) => [
                'label' => $page['label'],
                'subtitle' => 'Page',
                'url' => $page['url'],
                'icon' => $page['icon'],
                'meta' => 'Page',
            ])
            ->values()
            ->all();

        if ($pages !== []) {
            $groups['Pages'] = $pages;
        }
    }

    private function appendMembers(array &$groups, string $needle, User $viewer): void
    {
        $members = BranchScope::applyToUsers(
            User::query()->memberUsers()->with('personalData'),
            $viewer
        )->where(function ($query) use ($needle) {
                $query->where('user_number', 'like', $needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhereHas('personalData', fn ($pd) => $pd
                        ->where('first_name', 'like', $needle)
                        ->orWhere('mid_name', 'like', $needle)
                        ->orWhere('last_name', 'like', $needle));
            })
            ->limit(6)
            ->get()
            ->map(fn (User $member) => [
                'label' => trim(($member->personalData->first_name ?? '').' '.($member->personalData->last_name ?? '')),
                'subtitle' => $member->user_number,
                'url' => '/dashboard/members',
                'icon' => 'user',
                'meta' => 'Member',
            ]);

        if ($members->isNotEmpty()) {
            $groups['Members'] = $members->values()->all();
        }
    }

    private function appendOfficers(array &$groups, string $needle, User $viewer): void
    {
        $officers = BranchScope::applyToUsers(
            User::query()->officerUsers()->with('personalData'),
            $viewer
        )->where(function ($query) use ($needle) {
                $query->where('user_number', 'like', $needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhereHas('personalData', fn ($pd) => $pd
                        ->where('first_name', 'like', $needle)
                        ->orWhere('mid_name', 'like', $needle)
                        ->orWhere('last_name', 'like', $needle));
            })
            ->limit(6)
            ->get()
            ->map(fn (User $officer) => [
                'label' => trim(($officer->personalData->first_name ?? '').' '.($officer->personalData->last_name ?? '')),
                'subtitle' => $officer->user_number,
                'url' => '/dashboard/officers',
                'icon' => 'user-tie',
                'meta' => 'Officer',
            ]);

        if ($officers->isNotEmpty()) {
            $groups['Officers'] = $officers->values()->all();
        }
    }

    private function appendLoans(array &$groups, string $needle, string $url, ?int $userId = null, ?User $viewer = null): void
    {
        $query = Loan::query()->with(['user.personalData']);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } elseif ($viewer !== null) {
            BranchScope::applyToLoans($query, $viewer);
        }

        $loans = $query->where(function ($q) use ($needle) {
            $q->where('loan_number', 'like', $needle)
                ->orWhere('loan_status', 'like', $needle)
                ->orWhereHas('user', fn ($sub) => $sub->where('user_number', 'like', $needle))
                ->orWhereHas('user.personalData', fn ($sub) => $sub
                    ->where('first_name', 'like', $needle)
                    ->orWhere('last_name', 'like', $needle));
        })
            ->limit(6)
            ->get()
            ->map(fn (Loan $loan) => [
                'label' => $loan->loan_number,
                'subtitle' => trim(($loan->user->personalData->first_name ?? '').' '.($loan->user->personalData->last_name ?? '')),
                'url' => $url,
                'icon' => 'file-invoice-dollar',
                'meta' => ucfirst($loan->loan_status),
            ]);

        if ($loans->isNotEmpty()) {
            $groups['Loans'] = $loans->values()->all();
        }
    }

    private function appendLedger(array &$groups, string $needle, User $viewer): void
    {
        $transactions = BranchScope::applyToTransactions(
            Transaction::query()->with(['account.user.personalData', 'loan']),
            $viewer
        )->where(function ($query) use ($needle) {
                $query->where('transaction_number', 'like', $needle)
                    ->orWhere('status', 'like', $needle)
                    ->orWhere('transaction_type', 'like', $needle)
                    ->orWhere('note', 'like', $needle)
                    ->orWhereHas('account.user', fn ($sub) => $sub->where('user_number', 'like', $needle))
                    ->orWhereHas('account.user.personalData', fn ($sub) => $sub
                        ->where('first_name', 'like', $needle)
                        ->orWhere('last_name', 'like', $needle));
            })
            ->orderByDesc('transaction_date')
            ->limit(6)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'label' => $transaction->transaction_number,
                'subtitle' => ucfirst(str_replace('_', ' ', $transaction->transaction_type)),
                'url' => '/dashboard/ledger',
                'icon' => 'book',
                'meta' => ucfirst($transaction->status),
            ]);

        if ($transactions->isNotEmpty()) {
            $groups['Ledger'] = $transactions->values()->all();
        }
    }

    private function appendHistory(array &$groups, string $needle, User $user): void
    {
        $userAccounts = $user->accounts->pluck('id');

        if ($userAccounts->isEmpty()) {
            return;
        }

        $transactions = Transaction::query()->with(['account', 'loan'])
            ->whereIn('account_id', $userAccounts)
            ->where(function ($query) use ($needle) {
                $query->where('transaction_number', 'like', $needle)
                    ->orWhere('status', 'like', $needle)
                    ->orWhere('transaction_type', 'like', $needle)
                    ->orWhere('note', 'like', $needle);
            })
            ->orderByDesc('transaction_date')
            ->limit(6)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'label' => $transaction->transaction_number,
                'subtitle' => ucfirst(str_replace('_', ' ', $transaction->transaction_type)),
                'url' => '/dashboard/history',
                'icon' => 'clock-rotate-left',
                'meta' => ucfirst($transaction->status),
            ]);

        if ($transactions->isNotEmpty()) {
            $groups['My Transactions'] = $transactions->values()->all();
        }
    }

    private function appendExpenses(array &$groups, string $needle, User $viewer): void
    {
        $expenses = BranchScope::applyToExpenses(Expense::query(), $viewer)
            ->where(function ($query) use ($needle) {
                $query->where('description', 'like', $needle)
                    ->orWhere('category', 'like', $needle);
            })
            ->orderByDesc('expense_date')
            ->limit(6)
            ->get()
            ->map(fn (Expense $expense) => [
                'label' => $expense->description,
                'subtitle' => $expense->category,
                'url' => '/dashboard/expenses',
                'icon' => 'receipt',
                'meta' => number_format((float) $expense->amount, 2),
            ]);

        if ($expenses->isNotEmpty()) {
            $groups['Expenses'] = $expenses->values()->all();
        }
    }

    private function appendAnnouncements(array &$groups, string $needle): void
    {
        $announcements = Announcement::query()
            ->where(function ($query) use ($needle) {
                $query->where('title', 'like', $needle)
                    ->orWhere('content', 'like', $needle);
            })
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(fn (Announcement $announcement) => [
                'label' => $announcement->title,
                'subtitle' => ucfirst($announcement->type),
                'url' => '/dashboard/announcements',
                'icon' => 'bullhorn',
                'meta' => 'Announcement',
            ]);

        if ($announcements->isNotEmpty()) {
            $groups['Announcements'] = $announcements->values()->all();
        }
    }

    private function appendLoanProducts(array &$groups, string $needle): void
    {
        $products = LoanProduct::query()
            ->where(function ($query) use ($needle) {
                $query->where('code', 'like', $needle)
                    ->orWhere('name', 'like', $needle);
            })
            ->orderBy('sort_order')
            ->limit(6)
            ->get()
            ->map(fn (LoanProduct $product) => [
                'label' => $product->name,
                'subtitle' => strtoupper($product->code),
                'url' => '/dashboard/loan-products',
                'icon' => 'tags',
                'meta' => ucfirst($product->base_type).' loan',
            ]);

        if ($products->isNotEmpty()) {
            $groups['Loan Products'] = $products->values()->all();
        }
    }

    private function appendRoles(array &$groups, string $needle): void
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', $needle)
            ->limit(6)
            ->get()
            ->map(fn (Role $role) => [
                'label' => ucwords(str_replace(['-', '_'], ' ', $role->name)),
                'subtitle' => 'Role',
                'url' => '/dashboard/role-permissions',
                'icon' => 'shield-halved',
                'meta' => 'Role',
            ]);

        if ($roles->isNotEmpty()) {
            $groups['Roles'] = $roles->values()->all();
        }
    }

    private function appendLogs(array &$groups, string $needle): void
    {
        $logs = Activity::query()
            ->where('description', 'like', $needle)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(fn (Activity $log) => [
                'label' => $log->description,
                'subtitle' => ucfirst(str_replace('.', ' ', $log->event ?? 'activity')),
                'url' => '/dashboard/logs',
                'icon' => 'list-check',
                'meta' => $log->created_at?->format('M d, Y'),
            ]);

        if ($logs->isNotEmpty()) {
            $groups['Logs'] = $logs->values()->all();
        }
    }
}

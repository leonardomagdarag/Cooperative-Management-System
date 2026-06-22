<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $role = $user->primary_role;

        return match ($role) {
            'admin' => $this->adminOverview(),
            'manager' => $this->managerOverview(),
            'accountant', 'treasurer' => $this->treasurerOverview(),
            'secretary' => $this->secretaryOverview(),
            'loan' => $this->loanOverview(),
            default => $this->memberOverview(),
        };
    }

    private function memberOverview(): JsonResponse
    {
        $user = Auth::user()->load('accounts', 'loans');

        return response()->json([
            'total_balance' => $user->accounts->sum('balance'),
            'accounts' => $user->accounts->map(fn ($a) => [
                'id' => $a->id, 'account_number' => $a->account_number,
                'account_type' => $a->account_type, 'balance' => $a->balance,
            ]),
            'active_loans_count' => $user->loans()->whereIn('loan_status', ['approved', 'disbursed', 'active'])->count(),
            'loans' => $user->loans()->latest()->take(5)->get()->map(fn ($l) => [
                'id' => $l->id, 'loan_number' => $l->loan_number,
                'loan_type' => $l->loan_type, 'loan_amount' => $l->loan_amount,
                'loan_status' => $l->loan_status,
            ]),
            'announcements' => Announcement::query()->latest()->take(3)->get()->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'type' => $a->type,
                'created_at' => $a->created_at->format('M d, Y'),
            ]),
        ]);
    }

    private function adminOverview(): JsonResponse
    {
        $totalMembers = User::query()->memberUsers()->count();
        $totalOfficers = User::query()->officerUsers()->count();
        $totalSavings = DB::table('accounts')->where('account_type', 'savings')->sum('balance');
        $totalShareCapital = DB::table('accounts')->where('account_type', 'share-capital')->sum('balance');
        $pendingTransactions = Transaction::where('status', 'pending')->count();
        $pendingLoans = Loan::where('loan_status', 'pending')->count();
        $activeLoans = Loan::whereIn('loan_status', ['approved', 'disbursed', 'active'])->count();

        return response()->json([
            'total_members' => $totalMembers,
            'total_officers' => $totalOfficers,
            'total_savings' => $totalSavings,
            'total_share_capital' => $totalShareCapital,
            'pending_transactions' => $pendingTransactions,
            'pending_loans' => $pendingLoans,
            'active_loans' => $activeLoans,
        ]);
    }

    private function managerOverview(): JsonResponse
    {
        return $this->adminOverview();
    }

    private function treasurerOverview(): JsonResponse
    {
        $totalSavings = DB::table('accounts')->where('account_type', 'savings')->sum('balance');
        $totalShareCapital = DB::table('accounts')->where('account_type', 'share-capital')->sum('balance');
        $recentTransactions = Transaction::with('account.user.personalData')->latest()->take(5)->get()
            ->map(fn ($t) => [
                'id' => $t->id, 'transaction_number' => $t->transaction_number,
                'transaction_type' => $t->transaction_type, 'amount' => $t->amount,
                'status' => $t->status,
            ]);

        return response()->json([
            'total_savings' => $totalSavings,
            'total_share_capital' => $totalShareCapital,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    private function secretaryOverview(): JsonResponse
    {
        $totalMembers = User::query()->memberUsers()->count();
        $newThisMonth = User::query()->memberUsers()->whereMonth('created_at', now()->month)->count();
        $totalAnnouncements = Announcement::count();

        return response()->json([
            'total_members' => $totalMembers,
            'new_this_month' => $newThisMonth,
            'total_announcements' => $totalAnnouncements,
        ]);
    }

    private function loanOverview(): JsonResponse
    {
        $pendingLoans = Loan::where('loan_status', 'pending')->count();
        $activeLoans = Loan::whereIn('loan_status', ['approved', 'disbursed', 'active'])->count();
        $overdueLoans = Loan::where('loan_status', 'active')->where('due_date', '<', now())->count();
        $outstandingAmount = Loan::whereIn('loan_status', ['approved', 'disbursed', 'active'])->sum('loan_amount');

        return response()->json([
            'pending_loans' => $pendingLoans,
            'active_loans' => $activeLoans,
            'overdue_loans' => $overdueLoans,
            'outstanding_amount' => $outstandingAmount,
        ]);
    }
}

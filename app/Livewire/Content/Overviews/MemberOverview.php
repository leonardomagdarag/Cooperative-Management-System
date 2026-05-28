<?php

namespace App\Livewire\Content\Overviews;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MemberOverview extends Component
{
    public $savingsAccount;
    public $shareCapitalAccount;
    public $activeLoans;
    public $recentTransactions;
    public $announcements;
    public $totalSavings = 0;
    public $totalShareCapital = 0;
    public $totalLoanBalance = 0;
    public $upcomingPayments = [];

    public function mount()
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        $user = Auth::user();

        // Load user with all necessary relationships
        $member = User::with([
            'accounts',
            'loans.payments',
            'personalData'
        ])->find($user->id);

        // Get savings and share capital accounts
        $this->savingsAccount = $member->accounts?->where('account_type', 'savings')->first();
        $this->shareCapitalAccount = $member->accounts?->where('account_type', 'share-capital')->first();

        // Calculate totals
        $this->totalSavings = $this->savingsAccount?->balance ?? 0;
        $this->totalShareCapital = $this->shareCapitalAccount?->balance ?? 0;

        // Get active loans
        $this->activeLoans = $member->loans?->whereIn('loan_status', ['approved', 'active'])->values();

        // Calculate total loan balance
        $this->totalLoanBalance = $this->activeLoans->sum(function ($loan) {
            return $loan->remainingBalance();
        });

        // Get upcoming payments (next 30 days)
        $this->upcomingPayments = $this->activeLoans->filter(function ($loan) {
            return $loan->due_date && \Carbon\Carbon::parse($loan->due_date)->diffInDays(now(), false) <= 30;
        })->sortBy('due_date')->take(5)->values();

        // Load recent announcements
        $this->announcements = \App\Models\Announcement::with('creator.personalData')
            ->latest()
            ->take(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.content.overviews.member-overview');
    }
}

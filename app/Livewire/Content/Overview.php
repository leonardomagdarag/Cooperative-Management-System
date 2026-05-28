<?php

namespace App\Livewire\Content;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Overview extends Component
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
        $this->totalLoanBalance = $this->activeLoans->sum(function($loan) {
            return $loan->remainingBalance();
        });

        // Get upcoming payments (next 30 days)
        $this->upcomingPayments = $this->activeLoans->filter(function($loan) {
            return $loan->due_date && \Carbon\Carbon::parse($loan->due_date)->diffInDays(now(), false) <= 30;
        })->sortBy('due_date')->take(5)->values();

        // Mock announcements (you can replace with actual model later)
        $this->announcements = collect([
            [
                'id' => 1,
                'title' => 'Holiday Closure Notice',
                'content' => 'Our office will be closed from December 24-26, 2025. Happy Holidays!',
                'date' => '2025-12-15',
                'type' => 'info'
            ],
            [
                'id' => 2,
                'title' => 'New Loan Products Available',
                'content' => 'We are now offering educational loans with lower interest rates. Visit our office for more details.',
                'date' => '2025-12-10',
                'type' => 'success'
            ],
            [
                'id' => 3,
                'title' => 'Annual General Meeting',
                'content' => 'Join us for our Annual General Meeting on January 15, 2026 at 2:00 PM.',
                'date' => '2025-12-08',
                'type' => 'warning'
            ]
        ]);
    }

    public function render()
    {
        return view('livewire.content.overview');
    }
}

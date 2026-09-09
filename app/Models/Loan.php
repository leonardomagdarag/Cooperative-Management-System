<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_number',
        'user_id',
        'branch_id',
        'account_id',
        'loan_type',
        'loan_amount',
        'interest_rate',
        'term_months',
        'payment_frequency',
        'loan_status',
        // Officer review lifecycle
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'applied_at',
        'approved_at',
        'approved_by',
        'due_date',
        'employment_id',
        'interview_schedule',
        'interview_status',
        'interview_officer_id',
        'interview_notes',
    ];

    protected $casts = [
        'loan_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'due_date' => 'date',
        'interview_schedule' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($loan) {
            if (empty($loan->loan_number)) {
                // Get the latest loan_number including soft deleted records
                $latest = static::withTrashed()
                    ->orderByRaw('CAST(SUBSTRING(loan_number, 5) AS UNSIGNED) DESC')
                    ->first();

                if ($latest && preg_match('/LOAN(\d+)/', $latest->loan_number, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    $nextNumber = 1;
                }

                $loan->loan_number = 'LOAN'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payments()
    {
        return $this->hasMany(Transaction::class)->where('transaction_type', 'loan_payment');
    }

    public function completedPayments()
    {
        return $this->payments()->where('status', 'completed');
    }

    public function totalPaid()
    {
        return $this->completedPayments()->sum('amount');
    }

    public function totalInterest(): float
    {
        $principal = (float) $this->loan_amount;
        $months = max(1, (int) ($this->term_months ?? 0));
        $monthlyRate = (float) $this->interest_rate / 100;

        if ($principal <= 0 || $monthlyRate <= 0) {
            return 0.0;
        }

        return round($principal * $monthlyRate * $months, 2);
    }

    public function monthlyPayment(): float
    {
        $months = max(1, (int) ($this->term_months ?? 0));

        return round($this->totalPayable() / $months, 2);
    }

    public function totalPayable(): float
    {
        $principal = (float) $this->loan_amount;

        if ($principal <= 0) {
            return 0.0;
        }

        return round($principal + $this->totalInterest(), 2);
    }

    public function remainingBalance()
    {
        return max(0, round($this->totalPayable() - $this->totalPaid(), 2));
    }

    /**
     * Return the instalments that reconcile exactly to this loan's payable
     * amount. Amounts are split in cents so rounding cannot leave a balance
     * after the final scheduled payment.
     */
    public function paymentSchedule(): ?array
    {
        $term = (int) ($this->term_months ?? 0);
        if ($term <= 0) {
            return null;
        }

        $principalCents = (int) round((float) $this->loan_amount * 100);
        $payableCents = (int) round($this->totalPayable() * 100);
        $interestCents = max(0, $payableCents - $principalCents);
        $startDate = ($this->approved_at ?? $this->applied_at ?? $this->created_at)?->copy();

        $principalPerPeriod = intdiv($principalCents, $term);
        $principalRemainder = $principalCents % $term;
        $interestPerPeriod = intdiv($interestCents, $term);
        $interestRemainder = $interestCents % $term;
        $balanceCents = $principalCents;
        $totalInterestCents = 0;
        $totalPaymentCents = 0;
        $schedule = [];

        for ($period = 1; $period <= $term; $period++) {
            $principal = $principalPerPeriod + ($period === $term ? $principalRemainder : 0);
            $interest = $interestPerPeriod + ($period === $term ? $interestRemainder : 0);
            $payment = $principal + $interest;

            $balanceCents -= $principal;
            $totalInterestCents += $interest;
            $totalPaymentCents += $payment;

            $schedule[] = [
                'period' => $period,
                'due_date' => $startDate?->copy()->addMonths($period)->format('M d, Y'),
                'payment' => $payment / 100,
                'principal' => $principal / 100,
                'interest' => $interest / 100,
                'balance' => max(0, $balanceCents) / 100,
                'total_interest' => $totalInterestCents / 100,
                'total_payment' => $totalPaymentCents / 100,
            ];
        }

        return $schedule;
    }

    public function employment()
    {
        return $this->belongsTo(Employment::class);
    }

    public function interviewOfficer()
    {
        return $this->belongsTo(User::class, 'interview_officer_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Human-readable review status label.
     */
    public function getReviewStatusLabelAttribute(): string
    {
        return match ($this->review_status) {
            'under_review' => 'Under Review',
            'endorsed_for_approval' => 'Endorsed for Approval',
            'review_rejected' => 'Rejected in Review',
            default => 'Awaiting Review',
        };
    }

    /**
     * Tailwind colour key for the review status badge.
     */
    public function getReviewStatusColorAttribute(): string
    {
        return match ($this->review_status) {
            'under_review' => 'blue',
            'endorsed_for_approval' => 'amber',
            'review_rejected' => 'red',
            default => 'gray',
        };
    }

    public function getLoanTypeLabelAttribute(): string
    {
        return ucwords(str_replace(['_', '-'], ' ', (string) $this->loan_type));
    }

    public function groupMembers()
    {
        return $this->belongsToMany(User::class, 'group_loan_members')
            ->withPivot('has_approved', 'approved_at')
            ->withTimestamps();
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function documents()
    {
        return $this->hasMany(LoanDocument::class)->latest('created_at');
    }
}

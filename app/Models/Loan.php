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
        'loan_product',
        'loan_amount',
        'interest_rate',
        'term_months',
        'payment_frequency',
        'loan_status',
        // Officer review lifecycle
        'review_status',
        'reviewed_by',
        'encoded_by',
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

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product', 'code');
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
        return (float) $this->completedPayments()
            ->selectRaw('COALESCE(SUM(CASE WHEN reversal_of_id IS NULL THEN amount ELSE -amount END), 0) as net_paid')
            ->value('net_paid');
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

    public function getCollectionMetrics(): array
    {
        $schedule = $this->paymentSchedule();
        if (! $schedule) {
            return ['amount_due' => 0.0, 'days_overdue' => 0];
        }

        $totalPaid = (float) $this->totalPaid();
        $amountDue = 0.0;
        $daysOverdue = 0;
        $now = now()->startOfDay();

        $cumulativeDue = 0.0;
        $firstUnpaidDueDate = null;

        foreach ($schedule as $installment) {
            $dueDate = \Carbon\Carbon::createFromFormat('M d, Y', $installment['due_date'])->startOfDay();

            if ($dueDate->lessThanOrEqualTo($now)) {
                $cumulativeDue += (float) $installment['payment'];

                if ($firstUnpaidDueDate === null && $totalPaid < ($cumulativeDue - 0.001)) { // tolerance for float precision
                    $firstUnpaidDueDate = $dueDate;
                }
            } else {
                break;
            }
        }

        $amountDue = max(0.0, $cumulativeDue - $totalPaid);

        if ($amountDue > 0 && $firstUnpaidDueDate !== null) {
            $daysOverdue = (int) $firstUnpaidDueDate->diffInDays($now);
        }

        return [
            'amount_due' => round($amountDue, 2),
            'days_overdue' => $daysOverdue,
        ];
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

    public function scopeApplyRange($query, ?string $range = 'today')
    {
        $range = $range ?: 'today';
        $now = now();

        return match ($range) {
            'today' => $query->where(function ($q) use ($now) {
                $q->whereDate('applied_at', $now->toDateString())
                    ->orWhere(function ($q2) use ($now) {
                        $q2->whereNull('applied_at')->whereDate('created_at', $now->toDateString());
                    });
            }),
            'week' => $query->where(function ($q) use ($now) {
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                $q->whereBetween('applied_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('applied_at')->whereBetween('created_at', [$start, $end]);
                    });
            }),
            'month' => $query->where(function ($q) use ($now) {
                $q->where(function ($q2) use ($now) {
                    $q2->whereMonth('applied_at', $now->month)
                        ->whereYear('applied_at', $now->year);
                })->orWhere(function ($q2) use ($now) {
                    $q2->whereNull('applied_at')
                        ->whereMonth('created_at', $now->month)
                        ->whereYear('created_at', $now->year);
                });
            }),
            'year' => $query->where(function ($q) use ($now) {
                $q->whereYear('applied_at', $now->year)
                    ->orWhere(function ($q2) use ($now) {
                        $q2->whereNull('applied_at')->whereYear('created_at', $now->year);
                    });
            }),
            'all' => $query,
            default => $query,
        };
    }
}

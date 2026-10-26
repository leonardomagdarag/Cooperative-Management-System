<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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
        'penalty_type',
        'penalty_amount',
        'penalty_frequency',
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
        'disbursed_at',
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
        'penalty_amount' => 'decimal:2',
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'due_date' => 'date',
        'interview_schedule' => 'datetime',
    ];

    /** Loans that are still actionable in the officer review queue. */
    public function scopeAwaitingReview($query)
    {
        return $query->where('loan_status', 'pending')
            ->where(function ($query) {
                $query->whereNull('review_status')
                    ->orWhereIn('review_status', ['under_review', 'endorsed_for_approval']);
            });
    }

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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function completedPayments()
    {
        return $this->payments()->where('status', 'completed');
    }

    public function totalPaid()
    {
        // Reuse the eager-loaded relation when present so callers that already
        // load `payments` (dashboards, profile, loan detail) do not issue a
        // query per loan. The aggregated result is identical either way.
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments
                ->where('status', 'completed')
                ->sum(fn ($p) => $p->reversal_of_id === null ? (float) $p->amount : -(float) $p->amount);
        }

        return (float) $this->completedPayments()
            ->selectRaw('COALESCE(SUM(CASE WHEN reversal_of_id IS NULL THEN amount ELSE -amount END), 0) as net_paid')
            ->value('net_paid');
    }

    /** Net amount received, excluding the portion applied to overdue penalties. */
    public function schedulePaid(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments
                ->where('status', 'completed')
                ->sum(fn ($p) => $p->reversal_of_id === null
                    ? (float) $p->amount - (float) ($p->penalty_amount ?? 0)
                    : -((float) $p->amount - (float) ($p->penalty_amount ?? 0)));
        }

        return (float) $this->completedPayments()
            ->selectRaw('COALESCE(SUM(CASE WHEN reversal_of_id IS NULL THEN amount - penalty_amount ELSE -(amount - penalty_amount) END), 0) as net_paid')
            ->value('net_paid');
    }

    public function penaltyPaid(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments
                ->where('status', 'completed')
                ->sum(fn ($p) => $p->reversal_of_id === null
                    ? (float) ($p->penalty_amount ?? 0)
                    : -(float) ($p->penalty_amount ?? 0));
        }

        return (float) $this->completedPayments()
            ->selectRaw('COALESCE(SUM(CASE WHEN reversal_of_id IS NULL THEN penalty_amount ELSE -penalty_amount END), 0) as net_paid')
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
        return max(0, round($this->totalPayable() - $this->schedulePaid(), 2)) + $this->accruedPenalty();
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
        // Repayment begins only when the borrower receives the funds.
        $startDate = $this->disbursed_at?->copy();
        if (! $startDate) {
            return null;
        }

        $principalPerPeriod = intdiv($principalCents, $term);
        $principalRemainder = $principalCents % $term;
        $interestPerPeriod = intdiv($interestCents, $term);
        $interestRemainder = $interestCents % $term;
        $balanceCents = $principalCents;
        $totalInterestCents = 0;
        $totalPaymentCents = 0;
        $schedule = [];
        $totalPaid = (float) $this->schedulePaid();
        $explicitAllocations = DB::table('loan_payment_installment_allocations as allocations')
            ->join('transactions', 'transactions.id', '=', 'allocations.transaction_id')
            ->where('transactions.loan_id', $this->id)
            ->where('transactions.status', 'completed')
            ->selectRaw('allocations.period, SUM(CASE WHEN transactions.reversal_of_id IS NULL THEN allocations.amount ELSE -allocations.amount END) as amount')
            ->groupBy('allocations.period')
            ->pluck('amount', 'period')
            ->map(fn ($amount) => max(0.0, (float) $amount));
        $legacyPaid = max(0.0, $totalPaid - (float) $explicitAllocations->sum());
        $paidBefore = 0.0;
        $now = now()->startOfDay();

        for ($period = 1; $period <= $term; $period++) {
            $principal = $principalPerPeriod + ($period === $term ? $principalRemainder : 0);
            $interest = $interestPerPeriod + ($period === $term ? $interestRemainder : 0);
            $payment = $principal + $interest;

            $balanceCents -= $principal;
            $totalInterestCents += $interest;
            $totalPaymentCents += $payment;

            $dueDate = $startDate?->copy()->addMonths($period)->startOfDay();
            $explicitPaid = min($payment / 100, (float) ($explicitAllocations[$period] ?? 0));
            $legacyApplied = min(max(0.0, $payment / 100 - $explicitPaid), $legacyPaid);
            $legacyPaid -= $legacyApplied;
            $paidForInstallment = min($payment / 100, $explicitPaid + $legacyApplied);
            $unpaidInstallment = max(0.0, $payment / 100 - $paidForInstallment);
            $paidBefore += $payment / 100;
            $penalty = 0.0;

            if ($dueDate && $dueDate->lessThan($now) && $unpaidInstallment > 0.001 && (float) $this->penalty_amount > 0) {
                $daysLate = (int) $dueDate->diffInDays($now);
                $periods = match ($this->penalty_frequency) {
                    'daily' => $daysLate,
                    'weekly' => intdiv($daysLate, 7),
                    default => 1,
                };

                if ($periods > 0) {
                    $chargePerPeriod = $this->penalty_type === 'percent'
                        ? $unpaidInstallment * (float) $this->penalty_amount / 100
                        : (float) $this->penalty_amount;
                    $penalty = round(round($chargePerPeriod, 2) * $periods, 2);
                }
            }

            $schedule[] = [
                'period' => $period,
                'due_date' => $dueDate?->format('M d, Y'),
                'payment' => $payment / 100,
                'paid' => $paidForInstallment,
                'amount_due' => max(0.0, $payment / 100 - $paidForInstallment),
                'principal' => $principal / 100,
                'interest' => $interest / 100,
                'penalty' => $penalty,
                'balance' => max(0, $balanceCents) / 100,
                'total_interest' => $totalInterestCents / 100,
                'total_payment' => $totalPaymentCents / 100,
            ];
        }

        // Penalty payments are stored on the loan transaction rather than an
        // installment allocation. Apply them oldest-overdue-first so the
        // schedule exposes the same remaining penalty total as collection
        // metrics and payment validation.
        $penaltyPaid = max(0.0, $this->penaltyPaid());
        foreach ($schedule as &$installment) {
            $applied = min((float) $installment['penalty'], $penaltyPaid);
            $installment['penalty'] = round((float) $installment['penalty'] - $applied, 2);
            $penaltyPaid -= $applied;
        }
        unset($installment);

        return $schedule;
    }

    public function getCollectionMetrics(): array
    {
        $schedule = $this->paymentSchedule();
        if (! $schedule) {
            return ['amount_due' => 0.0, 'days_overdue' => 0, 'accrued_penalty' => 0.0];
        }

        $amountDue = 0.0;
        $daysOverdue = 0;
        $now = now()->startOfDay();

        $firstUnpaidDueDate = null;
        $accruedPenalty = 0.0;

        foreach ($schedule as $installment) {
            $dueDate = Carbon::createFromFormat('M d, Y', $installment['due_date'])->startOfDay();

            if ($dueDate->lessThanOrEqualTo($now)) {
                $unpaidInstallment = (float) $installment['amount_due'];
                $amountDue += $unpaidInstallment;

                if ($unpaidInstallment > 0.001) {
                    if ($firstUnpaidDueDate === null) {
                        $firstUnpaidDueDate = $dueDate;
                    }
                    $accruedPenalty += (float) $installment['penalty'];
                }
            } else {
                break;
            }
        }

        $penaltyDue = $accruedPenalty;
        $amountDue += $penaltyDue;

        if ($amountDue > 0 && $firstUnpaidDueDate !== null) {
            $daysOverdue = (int) $firstUnpaidDueDate->diffInDays($now);
        }

        return [
            'amount_due' => round($amountDue, 2),
            'days_overdue' => $daysOverdue,
            'accrued_penalty' => round($penaltyDue, 2),
        ];
    }

    public function accruedPenalty(): float
    {
        return (float) ($this->getCollectionMetrics()['accrued_penalty'] ?? 0);
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

    public function collectionRunItems(): HasMany
    {
        return $this->hasMany(CollectionRunItem::class);
    }

    /** Explain why a loan must remain visible in the borrower's record. */
    public function deletionBlockReason(): ?string
    {
        if ($this->loan_status !== 'pending' || $this->approved_at || $this->approved_by) {
            return 'Only pending loans that have not been approved can be deleted.';
        }

        if ($this->review_status !== null || $this->reviewed_at || $this->reviewed_by) {
            return 'Loans with review history cannot be deleted. Reject the application to preserve its history.';
        }

        foreach ([
            ['transactions_count', 'transactions', 'financial transactions'],
            ['documents_count', 'documents', 'uploaded documents'],
            ['group_members_count', 'groupMembers', 'co-maker records'],
            ['collection_run_items_count', 'collectionRunItems', 'collection records'],
        ] as [$countAttribute, $relation, $description]) {
            $hasRecords = array_key_exists($countAttribute, $this->getAttributes())
                ? (int) $this->getAttribute($countAttribute) > 0
                : $this->{$relation}()->exists();

            if ($hasRecords) {
                return "Loans with {$description} cannot be deleted.";
            }
        }

        return null;
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

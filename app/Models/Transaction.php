<?php

namespace App\Models;

use App\Support\TransactionNumberAllocator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'client_request_id',
        'transaction_type',
        'transaction_mode',
        'account_id',
        'loan_id',
        'amount',
        'penalty_amount',
        'fee_amount',
        'transaction_date',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'proof_of_payment_path',
        'proof_of_payment_uploaded_at',
        'note',
        'recorded_by',
        'branch_id',
        'payment_gateway',
        'payment_method_used',
        'paymongo_checkout_session_id',
        'paymongo_payment_intent_id',
        'paymongo_payment_id',
        'paymongo_fee',
        'xendit_session_id',
        'xendit_mode',
        'xendit_payment_id',
        'reversal_of_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'paymongo_fee' => 'decimal:2',
        'transaction_date' => 'datetime',
        'approved_at' => 'datetime',
        'proof_of_payment_uploaded_at' => 'datetime',
    ];

    public static function netAmountExpression(): string
    {
        return 'CASE WHEN transactions.reversal_of_id IS NULL THEN transactions.amount ELSE -transactions.amount END';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            $allocator = app(TransactionNumberAllocator::class);

            if (empty($transaction->transaction_number)) {
                $transaction->transaction_number = $allocator->next();
            } else {
                $allocator->observe($transaction->transaction_number);
            }
        });
    }

    // Eloquent Relationships

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function user()
    {
        return $this->hasOneThrough(
            User::class,
            Account::class,
            'id',
            'id',
            'account_id',
            'user_id'
        );
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes for filtering by type

    public function scopeAwaitingXenditVerification($query)
    {
        return $query->where('status', 'pending')
            ->where('payment_gateway', 'xendit')
            ->whereIn('transaction_type', ['deposit', 'loan_payment'])
            ->whereNotNull('xendit_session_id')
            ->where('xendit_session_id', '!=', '')
            ->where(fn ($payments) => $payments->whereNull('xendit_payment_id')->orWhere('xendit_payment_id', ''));
    }

    public function scopeDeposits($query)
    {
        return $query->where('transaction_type', 'deposit');
    }

    public function scopeWithdrawals($query)
    {
        return $query->where('transaction_type', 'withdrawal');
    }

    public function scopeLoanPayments($query)
    {
        return $query->where('transaction_type', 'loan_payment');
    }

    public function scopeApplyRange($query, ?string $range = 'today')
    {
        $range = $range ?: 'today';
        $now = now();

        return match ($range) {
            'today' => $query->where(function ($q) use ($now) {
                $q->whereDate('transaction_date', $now->toDateString())
                    ->orWhere(function ($q2) use ($now) {
                        $q2->whereNull('transaction_date')->whereDate('created_at', $now->toDateString());
                    });
            }),
            'week' => $query->where(function ($q) use ($now) {
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                $q->whereBetween('transaction_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('transaction_date')->whereBetween('created_at', [$start, $end]);
                    });
            }),
            'month' => $query->where(function ($q) use ($now) {
                $q->where(function ($q2) use ($now) {
                    $q2->whereMonth('transaction_date', $now->month)
                        ->whereYear('transaction_date', $now->year);
                })->orWhere(function ($q2) use ($now) {
                    $q2->whereNull('transaction_date')
                        ->whereMonth('created_at', $now->month)
                        ->whereYear('created_at', $now->year);
                });
            }),
            'year' => $query->where(function ($q) use ($now) {
                $q->whereYear('transaction_date', $now->year)
                    ->orWhere(function ($q2) use ($now) {
                        $q2->whereNull('transaction_date')->whereYear('created_at', $now->year);
                    });
            }),
            'all' => $query,
            default => $query,
        };
    }
}

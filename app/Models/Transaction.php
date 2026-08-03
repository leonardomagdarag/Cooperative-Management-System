<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'transaction_type',
        'transaction_mode',
        'account_id',
        'loan_id',
        'amount',
        'transaction_date',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'proof_of_payment_path',
        'proof_of_payment_uploaded_at',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'approved_at' => 'datetime',
        'proof_of_payment_uploaded_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->transaction_number)) {
                // Get the latest transaction_number for today
                $datePrefix = 'TXN'.date('Ymd');
                $latest = static::where('transaction_number', 'like', $datePrefix.'%')
                    ->orderByRaw('CAST(SUBSTRING(transaction_number, 12) AS UNSIGNED) DESC')
                    ->first();

                if ($latest && preg_match('/'.preg_quote($datePrefix, '/').'(\d+)/', $latest->transaction_number, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    $nextNumber = 1;
                }

                $transaction->transaction_number = $datePrefix.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
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

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Scopes for filtering by type
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
}

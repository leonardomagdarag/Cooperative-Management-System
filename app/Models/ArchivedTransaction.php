<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedTransaction extends Model
{
    protected $fillable = [
        'original_id',
        'reversal_of_id',
        'transaction_number',
        'transaction_type',
        'account_id',
        'loan_id',
        'amount',
        'fee_amount',
        'transaction_date',
        'note',
        'recorded_by',
        'created_at',
        'updated_at',
        'archived_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'archived_at' => 'datetime',
    ];

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

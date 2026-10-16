<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single member's line within an {@see InterestDisbursement} batch.
 *
 * A line is either a credit (linked to the generated `interest` transaction)
 * or a recorded skip (e.g. the amount fell below the minimum payout).
 */
class InterestDisbursementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'interest_disbursement_id',
        'user_id',
        'account_id',
        'transaction_id',
        'amount',
        'balance_before',
        'balance_after',
        'rate',
        'skipped',
        'skip_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'rate' => 'decimal:4',
        'skipped' => 'boolean',
    ];

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(InterestDisbursement::class, 'interest_disbursement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}

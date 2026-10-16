<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A batch run of interest credited to member financial accounts.
 *
 * One row per (account type, period). The per-member credits live in
 * {@see InterestDisbursementItem}.
 */
class InterestDisbursement extends Model
{
    use HasFactory;

    public const ACCOUNT_TYPE_SAVINGS = 'savings';

    public const ACCOUNT_TYPE_SHARE_CAPITAL = 'share-capital';

    protected $fillable = [
        'account_type',
        'rate',
        'interval',
        'period_label',
        'period_start',
        'period_end',
        'member_count',
        'skipped_count',
        'total_amount',
        'status',
        'trigger',
        'batch_key',
        'requested_by',
        'approved_by',
        'note',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'total_amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'member_count' => 'integer',
        'skipped_count' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InterestDisbursementItem::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

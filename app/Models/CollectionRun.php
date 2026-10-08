<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionRun extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'collection_date' => 'date',
            'stages_snapshot' => 'array',
            'total_amount' => 'decimal:2',
            'is_late' => 'boolean',
            'decided_at' => 'datetime',
            'submitted_at' => 'datetime',
            'orphaned_at' => 'datetime',
        ];
    }

    public function loanOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loan_officer_id')->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CollectionRunItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(CollectionRunApproval::class);
    }

    public function resubmissionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resubmission_of_id');
    }

    public function resubmission(): HasMany
    {
        return $this->hasMany(self::class, 'resubmission_of_id')->withTrashed();
    }
}

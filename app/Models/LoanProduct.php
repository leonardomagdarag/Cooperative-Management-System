<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'base_type',
        'icon',
        'interest_rate',
        'penalty_type',
        'penalty_amount',
        'penalty_frequency',
        'max_amount',
        'max_term',
        'is_active',
        'requires_no_existing_loan',
        'minimum_share_capital',
        'minimum_membership_years',
        'required_co_makers',
        'requires_interview',
        'requires_special_eligibility',
        'sort_order',
    ];

    protected $casts = [
        'interest_rate' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'requires_no_existing_loan' => 'boolean',
        'minimum_share_capital' => 'decimal:2',
        'minimum_membership_years' => 'integer',
        'required_co_makers' => 'integer',
        'requires_interview' => 'boolean',
        'requires_special_eligibility' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

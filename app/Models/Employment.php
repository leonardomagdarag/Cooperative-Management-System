<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employment_type',
        'company',
        'position',
        'monthly_income',
        'other_income_source',
        'other_income_amount',
    ];

    protected $casts = [
        'monthly_income' => 'decimal:2',
        'other_income_amount' => 'decimal:2',
    ];

    // Relationships
    public function personalData()
    {
        return $this->belongsTo(PersonalData::class, 'employment_id');
    }
}

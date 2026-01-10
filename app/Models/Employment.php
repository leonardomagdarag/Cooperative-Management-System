<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employment_type',
        'employment_status',
        'employer_name',
        'employer_contact',
        'position',
        'industry',
        'monthly_income',
        'date_hired',
        'years_employed',
        'supervisor_name',
        'employer_address',
        'previous_employer',
        'years_previous_employment',
        'other_income_source',
        'other_income_amount'
    ];

    protected $casts = [
        'date_hired' => 'date',
        'monthly_income' => 'decimal:2',
        'other_income_amount' => 'decimal:2',
    ];

    // Relationships
    public function personalData()
    {
        return $this->hasOne(PersonalData::class, 'employment_id');
    }
}

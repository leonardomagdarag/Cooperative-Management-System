<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class Loan extends Model
{
    protected $primaryKey = 'loan_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'loan_id', 'account_id', 'loan_type', 'loan_amount',
        'interest_rate', 'payment_frequency', 'loan_status',
        'approved_at', 'approved_by', 'due_date'
    ];

    //creating a custom primary key generator for user_id
    use GeneratesCustomId;

    // Customize trait settings
    protected $customIdColumn = 'loan_id';
    protected $customIdPrefix = 'LN_';
    protected $customIdPadLength = 4;

    // Boot the trait
    protected static function boot()
    {
        parent::boot();
        static::bootGeneratesCustomId();
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }

    public function payments()
    {
        return $this->hasMany(LoanPayment::class, 'loan_id', 'loan_id');
    }
}

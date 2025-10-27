<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class LoanPayment extends Model
{
    protected $primaryKey = 'payment_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'payment_id', 'loan_id', 'payment_amount'
    ];

    //creating a custom primary key generator for user_id
    use GeneratesCustomId;

    // Customize trait settings
    protected $customIdColumn = 'payment_id';
    protected $customIdPrefix = 'LNP_';
    protected $customIdPadLength = 4;

    // Boot the trait
    protected static function boot()
    {
        parent::boot();
        static::bootGeneratesCustomId();
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id', 'loan_id');
    }
}

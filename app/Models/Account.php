<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class Account extends Model
{
    protected $primaryKey = 'account_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'account_id',
        'user_id',
        'account_type',
        'balance'
    ];

    //creating a custom primary key generator for user_id
    use GeneratesCustomId;

    // Customize trait settings
    protected $customIdColumn = 'account_id';
    protected $customIdPrefix = 'ACNT_';
    protected $customIdPadLength = 4;

    // Boot the trait
    protected static function boot()
    {
        parent::boot();
        static::bootGeneratesCustomId();
    }

    //Eloquent Relationships

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'account_id', 'account_id');
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class, 'account_id', 'account_id');
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class, 'account_id', 'account_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'account_id', 'account_id');
    }
}

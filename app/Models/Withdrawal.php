<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class Withdrawal extends Model
{
    protected $primaryKey = 'withdrawal_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'withdrawal_id', 'account_id', 'withdrawal_amount', 'recorded_by'
    ];

    //creating a custom primary key generator for user_id
    use GeneratesCustomId;

    // Customize trait settings
    protected $customIdColumn = 'withdrawal_id';
    protected $customIdPrefix = 'WDL_';
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
}

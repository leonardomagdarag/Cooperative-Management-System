<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class Deposit extends Model
{
    protected $primaryKey = 'deposit_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'deposit_id', 'account_id', 'deposit_amount',
        'deposit_date', 'recorded_by'
    ];

    //creating a custom primary key generator for user_id
    use GeneratesCustomId;

    // Customize trait settings
    protected $customIdColumn = 'deposit_id';
    protected $customIdPrefix = 'DPT_';
    protected $customIdPadLength = 4;

    // Boot the trait
    protected static function boot()
    {
        parent::boot();
        static::bootGeneratesCustomId();
    }

    //Eloquent Relationships

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }
}

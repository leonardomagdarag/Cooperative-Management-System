<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class Transaction extends Model
{
    protected $primaryKey = 'transaction_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'transaction_id', 'account_id', 'processed_by',
        'transaction_type', 'amount'
    ];

    //creating a custom primary key generator for user_id
    use GeneratesCustomId;

    // Customize trait settings
    protected $customIdColumn = 'transaction_id';
    protected $customIdPrefix = 'TRX_';
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

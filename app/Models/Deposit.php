<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'deposit_number',
        'account_id',
        'deposit_amount',
        'deposit_date',
        'recorded_by'
    ];

    protected $casts = [
        'deposit_amount' => 'decimal:2',
        'deposit_date' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($deposit) {
            if (empty($deposit->deposit_number)) {
                // Get the latest deposit_number
                $latest = static::orderByRaw('CAST(SUBSTRING(deposit_number, 4) AS UNSIGNED) DESC')
                    ->first();
                
                if ($latest && preg_match('/DEP(\d+)/', $latest->deposit_number, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    $nextNumber = 1;
                }
                
                $deposit->deposit_number = 'DEP' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    //Eloquent Relationships

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

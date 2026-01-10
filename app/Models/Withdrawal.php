<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'withdrawal_number',
        'account_id',
        'withdrawal_amount',
        'recorded_by'
    ];

    protected $casts = [
        'withdrawal_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($withdrawal) {
            if (empty($withdrawal->withdrawal_number)) {
                // Get the latest withdrawal_number
                $latest = static::orderByRaw('CAST(SUBSTRING(withdrawal_number, 4) AS UNSIGNED) DESC')
                    ->first();
                
                if ($latest && preg_match('/WTH(\d+)/', $latest->withdrawal_number, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    $nextNumber = 1;
                }
                
                $withdrawal->withdrawal_number = 'WTH' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

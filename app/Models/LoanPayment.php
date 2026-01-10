<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number',
        'loan_id',
        'payment_amount',
        'recorded_by'
    ];

    protected $casts = [
        'payment_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($payment) {
            if (empty($payment->payment_number)) {
                // Get the latest payment_number
                $latest = static::orderByRaw('CAST(SUBSTRING(payment_number, 4) AS UNSIGNED) DESC')
                    ->first();
                
                if ($latest && preg_match('/PAY(\d+)/', $latest->payment_number, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    $nextNumber = 1;
                }
                
                $payment->payment_number = 'PAY' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

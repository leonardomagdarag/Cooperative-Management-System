<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_number',
        'user_id',
        'account_type',
        'balance',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($account) {
            if (empty($account->account_number)) {
                // Get the latest account_number including soft deleted records
                $latest = static::withTrashed()
                    ->orderByRaw('CAST(SUBSTRING(account_number, 5) AS UNSIGNED) DESC')
                    ->first();

                if ($latest && preg_match('/ACNT(\d+)/', $latest->account_number, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    $nextNumber = 1;
                }

                $account->account_number = 'ACNT'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    // Eloquent Relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Convenience methods using Transaction scopes
    public function deposits()
    {
        return $this->transactions()->deposits();
    }

    public function withdrawals()
    {
        return $this->transactions()->withdrawals();
    }

    public function loanPayments()
    {
        return $this->transactions()->loanPayments();
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'account_id');
    }
}

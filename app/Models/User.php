<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_number',
        'personal_data_id',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->user_number)) {
                // Get the latest user_number including soft deleted records
                $latest = static::withTrashed()
                    ->orderByRaw('CAST(SUBSTRING(user_number, 4) AS UNSIGNED) DESC')
                    ->first();
                
                if ($latest && preg_match('/USR(\d+)/', $latest->user_number, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    $nextNumber = 1;
                }
                
                $user->user_number = 'USR'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function hasRole($role)
    {
        if (strcmp($this->role, $role) == 0) {
            return true;
        }

        return false;
    }

    // eloquent relationships
    public function personalData()
    {
        return $this->belongsTo(PersonalData::class);
    }

    public function contact()
    {
        return $this->hasOneThrough(
            Contact::class,
            PersonalData::class,
            'id',
            'id',
            'personal_data_id',
            'contact_id'
        );
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function recordedTransactions()
    {
        return $this->hasMany(Transaction::class, 'recorded_by');
    }

    public function logs()
    {
        return $this->hasMany(Log::class);
    }

    public function approvedLoans()
    {
        return $this->hasMany(Loan::class, 'approved_by');
    }

    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class);
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\OfficerPermissionOverride;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles {
        // Alias the trait's version so we can call it from our override
        HasRoles::checkPermissionTo as spatieCheckPermissionTo;
    }

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
        'account_status',
        'is_officer',
        'special_loan_eligible',
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

    protected string $guard_name = 'web';

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
            'account_status' => 'string',
            'special_loan_eligible' => 'boolean',
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

                $user->user_number = 'USR' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }

            if (empty($user->account_status)) {
                $user->account_status = 'active';
            }
        });
    }

    public static function normalizeEmail(mixed $email): string
    {
        return strtolower(trim((string) $email));
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn(mixed $value) => static::normalizeEmail($value),
        );
    }

    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    public function isTerminated(): bool
    {
        return $this->account_status === 'terminated';
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

    public function approvedLoans()
    {
        return $this->hasMany(Loan::class, 'approved_by');
    }

    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function otpChallenges()
    {
        return $this->hasMany(OtpChallenge::class);
    }

    public function trustedDevices()
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function permissionOverrides()
    {
        return $this->hasMany(OfficerPermissionOverride::class);
    }

    /**
     * Override Spatie's checkPermissionTo so that per-officer overrides are
     * applied before the role check.  Disallow returns false (Spatie converts
     * this to null inside Gate::before, so the Gate's default deny takes over).
     * Allow grants access even when the role lacks the permission.
     */
    public function checkPermissionTo($permission, $guardName = null): bool
    {
        if ($this->is_officer) {
            $effect = OfficerPermissionOverride::getCachedEffect(
                $this->id,
                (string) $permission
            );

            if ($effect === OfficerPermissionOverride::EFFECT_DISALLOW) {
                return false;
            }

            $hasViaRole = $this->spatieCheckPermissionTo($permission, $guardName);

            if (! $hasViaRole) {
                return $effect === OfficerPermissionOverride::EFFECT_ALLOW;
            }

            return true;
        }

        return $this->spatieCheckPermissionTo($permission, $guardName);
    }
}

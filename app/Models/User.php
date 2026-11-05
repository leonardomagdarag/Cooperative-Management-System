<?php

namespace App\Models;

use App\Support\PermissionCatalog;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes {
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
        'branch_id',
        'email',
        'password',
        'must_set_password',
        'role',
        'account_status',
        'is_officer',
        'special_loan_eligible',
        'profile_completion_required',
        'profile_completed_at',
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
            'must_set_password' => 'boolean',
            'account_status' => 'string',
            'special_loan_eligible' => 'boolean',
            'profile_completion_required' => 'boolean',
            'profile_completed_at' => 'datetime',
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
            set: fn (mixed $value) => static::normalizeEmail($value),
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $personalData = $this->personalData;

                if (! $personalData) {
                    return '';
                }

                return trim(implode(' ', array_filter([
                    $personalData->first_name,
                    $personalData->last_name,
                ])));
            },
        );
    }

    protected function primaryRole(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): string {
                $assignedRole = $this->getRoleNames()->first();

                if (is_string($assignedRole) && $assignedRole !== '') {
                    return $assignedRole;
                }

                return (string) ($attributes['role'] ?? 'member');
            },
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

    /**
     * Determine whether the user still needs to complete their profile
     * (self-registered members capture a subset of fields via the public form).
     */
    public function requiresProfileCompletion(): bool
    {
        return (bool) $this->profile_completion_required;
    }

    public function memberApplication()
    {
        return $this->hasOne(MemberApplication::class);
    }

    public function scopeOfficerUsers(Builder $query): Builder
    {
        $legacyOfficerRoles = array_merge(['admin'], PermissionCatalog::officerRoles());

        return $query->where(function (Builder $builder) use ($legacyOfficerRoles) {
            $builder->where('is_officer', true)
                ->orWhereIn('role', $legacyOfficerRoles);
        });
    }

    public function scopeMemberUsers(Builder $query): Builder
    {
        return $query->whereHas('roles', function (Builder $roleQuery) {
            $roleQuery->where('name', 'member');
        })
            ->where(function (Builder $builder) {
                $builder->where('is_officer', false)
                    ->orWhereNull('is_officer');
            });
    }

    // eloquent relationships
    public function personalData()
    {
        return $this->belongsTo(PersonalData::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
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

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function isSupportStaff(): bool
    {
        return in_array($this->primary_role, ['admin', 'chairman', 'manager', 'accountant'], true)
            || $this->can('tickets.manage');
    }

    public function invalidateOtherSessions(?string $currentSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $this->getKey());

        if (filled($currentSessionId)) {
            $query->where('id', '!=', $currentSessionId);
        }

        $query->delete();
    }

    public function activateSession(string $sessionId): void
    {
        $this->forceFill(['current_session_id' => $sessionId])->save();
    }

    public function clearCurrentSession(?string $sessionId = null): void
    {
        $query = static::query()->whereKey($this->getKey());
        if (filled($sessionId)) {
            $query->where('current_session_id', $sessionId);
        }
        $query->update(['current_session_id' => null]);
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
        $permissionName = is_object($permission) && isset($permission->name)
            ? $permission->name
            : (string) $permission;

        if ($permissionName === 'oversight.approve') {
            return $this->hasRole('chairman')
                && OfficerPermissionOverride::getCachedEffect($this->id, 'oversight.approve') !== OfficerPermissionOverride::EFFECT_DISALLOW
                && $this->spatieCheckPermissionTo($permission, $guardName);
        }

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

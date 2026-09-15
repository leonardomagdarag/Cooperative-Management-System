<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'is_head_office',
        'address',
        'contact_number',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_head_office' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($branch) {
            if (empty($branch->code)) {
                $latest = static::withTrashed()
                    ->orderByRaw('CAST(SUBSTRING(code, 3) AS UNSIGNED) DESC')
                    ->first();

                if ($latest && preg_match('/BR(\d+)/', $latest->code, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    $nextNumber = 1;
                }

                $branch->code = 'BR'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isMain(): bool
    {
        return (bool) $this->is_head_office;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function memberApplications()
    {
        return $this->hasMany(MemberApplication::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'application_number',
        'original_email',
        'first_name',
        'last_name',
        'phone',
        'membership_type',
        'status',
        'decline_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($application) {
            if (empty($application->application_number)) {
                $latest = static::orderByRaw('CAST(SUBSTRING(application_number, 4) AS UNSIGNED) DESC')->first();

                if ($latest && preg_match('/APP(\d+)/', $latest->application_number, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    $nextNumber = 1;
                }

                $application->application_number = 'APP'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

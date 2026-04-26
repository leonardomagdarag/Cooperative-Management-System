<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpChallenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'purpose',
        'code_hash',
        'context_hash',
        'context',
        'attempts',
        'max_attempts',
        'expires_at',
        'consumed_at',
    ];

    protected $casts = [
        'context' => 'array',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->whereColumn('attempts', '<', 'max_attempts');
    }
}

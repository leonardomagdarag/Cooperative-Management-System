<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrustedDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fingerprint',
        'device_label',
        'last_ip_address',
        'user_agent',
        'trusted_until',
        'last_used_at',
        'is_active',
    ];

    protected $casts = [
        'trusted_until' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query
            ->where('is_active', true)
            ->where('trusted_until', '>', now());
    }
}

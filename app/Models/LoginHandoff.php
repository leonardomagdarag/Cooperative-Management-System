<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginHandoff extends Model
{
    protected $fillable = [
        'user_id', 'current_session_id', 'pending_session_id', 'device_label', 'ip_address',
        'status', 'expires_at', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}

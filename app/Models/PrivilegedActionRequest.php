<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivilegedActionRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'execution_result' => 'array',
        'requested_at' => 'datetime',
        'expires_at' => 'datetime',
        'decided_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'started_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

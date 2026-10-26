<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XenditWebhookEvent extends Model
{
    protected $fillable = ['event_id', 'event_type', 'payload', 'processed_at'];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestoreRun extends Model
{
    protected $fillable = ['tier', 'path', 'scope', 'status', 'message', 'rollback_path', 'started_at', 'finished_at', 'triggered_by'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}

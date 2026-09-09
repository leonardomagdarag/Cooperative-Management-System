<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'tier',
        'scope',
        'status',
        'filename',
        'size',
        'message',
        'started_at',
        'finished_at',
        'triggered_by',
    ];

    protected $casts = [
        'size' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}

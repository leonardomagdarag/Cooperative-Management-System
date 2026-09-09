<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Encrypted, database-backed credentials (e.g. the Google Drive OAuth
 * credentials used by the cloud backup tier). Values are encrypted at rest via
 * the "encrypted" cast so they never appear in plaintext in the database.
 */
class AppCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'label',
        'description',
    ];

    protected $casts = [
        'value' => 'encrypted',
    ];
}

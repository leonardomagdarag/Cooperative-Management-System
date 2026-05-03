<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficerPermissionOverride extends Model
{
    public const EFFECT_ALLOW    = 'allow';
    public const EFFECT_DISALLOW = 'disallow';

    protected $fillable = ['user_id', 'permission', 'effect'];

    /** Per-request in-memory cache — keyed by "userId||permission". */
    private static array $cache = [];

    /**
     * Reset the in-memory cache. Call after writes and in test setUp.
     */
    public static function clearCache(): void
    {
        static::$cache = [];
    }

    /**
     * Cached lookup of a single override effect for a user+permission pair.
     * Returns null when no override row exists.
     */
    public static function getCachedEffect(int $userId, string $permission): ?string
    {
        $key = $userId . '||' . $permission;
        if (! array_key_exists($key, static::$cache)) {
            $effect = static::where('user_id', $userId)
                ->where('permission', $permission)
                ->value('effect');
            // Store false as sentinel for "row not found"
            static::$cache[$key] = $effect ?? false;
        }
        $cached = static::$cache[$key];
        return $cached === false ? null : (string) $cached;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

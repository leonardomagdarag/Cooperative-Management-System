<?php

namespace App\Models;

use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class OfficerPermissionOverride extends Model
{
    use HasFactory;

    public const EFFECT_ALLOW = 'allow';

    public const EFFECT_DISALLOW = 'disallow';

    protected $fillable = [
        'user_id',
        'permission',
        'effect',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $override): void {
            self::forgetCachedEffect($override->user_id, $override->permission);
        });

        static::deleted(function (self $override): void {
            self::forgetCachedEffect($override->user_id, $override->permission);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function getCachedEffect(int $userId, string $permission): ?string
    {
        return Cache::rememberForever(
            self::cacheKey($userId, $permission),
            static fn (): ?string => self::query()
                ->where('user_id', $userId)
                ->where('permission', $permission)
                ->value('effect')
        );
    }

    public static function forgetAllCachedEffects(int $userId): void
    {
        foreach (PermissionCatalog::allPermissions() as $permission) {
            self::forgetCachedEffect($userId, $permission);
        }
    }

    public static function forgetCachedEffect(int $userId, string $permission): void
    {
        Cache::forget(self::cacheKey($userId, $permission));
    }

    private static function cacheKey(int $userId, string $permission): string
    {
        return sprintf('officer_permission_override:%d:%s', $userId, $permission);
    }
}

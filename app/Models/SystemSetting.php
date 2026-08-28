<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'options',
        'is_editable',
        'requires_restart',
    ];

    protected $casts = [
        'is_editable' => 'boolean',
        'requires_restart' => 'boolean',
        'options' => 'array',
    ];

    /**
     * Get setting value by key
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("system_setting.{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            // Parse value based on type
            return match ($setting->type) {
                'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'number', 'integer' => (int) $setting->value,
                'float', 'decimal' => (float) $setting->value,
                'json' => json_decode($setting->value, true),
                'array' => json_decode($setting->value, true),
                default => $setting->value,
            };
        });
    }

    /**
     * Set setting value
     */
    public static function set(string $key, $value): bool
    {
        $setting = static::where('key', $key)->first();

        if (! $setting || ! $setting->is_editable) {
            return false;
        }

        // Convert value to string based on type
        $stringValue = match ($setting->type) {
            'boolean' => $value ? '1' : '0',
            'json', 'array' => json_encode($value),
            default => (string) $value,
        };

        $setting->update(['value' => $stringValue]);
        Cache::forget("system_setting.{$key}");

        return true;
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group)
    {
        return static::where('group', $group)->get();
    }

    /**
     * Clear cache when setting is updated
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($setting) {
            Cache::forget("system_setting.{$setting->key}");
        });

        static::deleted(function ($setting) {
            Cache::forget("system_setting.{$setting->key}");
        });
    }
}

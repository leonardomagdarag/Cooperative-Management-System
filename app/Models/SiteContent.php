<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'section',
        'key',
        'value',
        'type',
        'description',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get content by section and key
     */
    public static function getContent(string $section, string $key, $default = null)
    {
        return Cache::remember("site_content.{$section}.{$key}", 3600, function () use ($section, $key, $default) {
            $content = static::where('section', $section)
                ->where('key', $key)
                ->where('is_active', true)
                ->first();

            return $content ? $content->value : $default;
        });
    }

    /**
     * Get all content for a section
     */
    public static function getSection(string $section)
    {
        return Cache::remember("site_content.section.{$section}", 3600, function () use ($section) {
            return static::where('section', $section)
                ->where('is_active', true)
                ->orderBy('order')
                ->get()
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Clear cache when content is updated
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($content) {
            Cache::forget("site_content.{$content->section}.{$content->key}");
            Cache::forget("site_content.section.{$content->section}");
        });

        static::deleted(function ($content) {
            Cache::forget("site_content.{$content->section}.{$content->key}");
            Cache::forget("site_content.section.{$content->section}");
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    const TYPE_NEWS         = 'news';
    const TYPE_ANNOUNCEMENT = 'announcement';
    const TYPE_EVENT        = 'event';

    const VISIBILITY_EVERYONE  = 'everyone';
    const VISIBILITY_MEMBERS   = 'members';
    const VISIBILITY_OFFICERS  = 'officers';
    const VISIBILITY_PUBLIC    = 'public';

    protected $fillable = [
        'title',
        'content',
        'type',
        'visibility',
        'category',
        'image_path',
        'event_date',
        'event_time',
        'event_location',
        'created_by',
    ];

    protected $casts = [
        'visibility' => 'array',
        'event_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Announcement $announcement) {
            if (empty($announcement->visibility)) {
                $announcement->visibility = [self::VISIBILITY_MEMBERS];
            }
        });
    }

    public function scopePublicNews($query)
    {
        return $query->whereIn('type', [self::TYPE_NEWS, self::TYPE_ANNOUNCEMENT])
            ->where(function ($q) {
                $q->whereJsonContains('visibility', self::VISIBILITY_PUBLIC)
                    ->orWhereJsonContains('visibility', self::VISIBILITY_EVERYONE);
            });
    }

    public function scopePublicEvents($query)
    {
        return $query->where('type', self::TYPE_EVENT)
            ->where(function ($q) {
                $q->whereJsonContains('visibility', self::VISIBILITY_PUBLIC)
                    ->orWhereJsonContains('visibility', self::VISIBILITY_EVERYONE);
            });
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->event_date !== null && $this->event_date->isFuture();
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_NEWS  => 'News',
            self::TYPE_EVENT => 'Event',
            default          => 'Announcement',
        };
    }

    public function getVisibilityLabelAttribute(): string
    {
        $visibility = (array) ($this->visibility ?? []);

        if (in_array(self::VISIBILITY_EVERYONE, $visibility, true)) {
            return 'Everyone';
        }

        $labels = array_filter([
            in_array(self::VISIBILITY_MEMBERS,  $visibility, true) ? 'Members'  : null,
            in_array(self::VISIBILITY_OFFICERS, $visibility, true) ? 'Officers' : null,
            in_array(self::VISIBILITY_PUBLIC,   $visibility, true) ? 'Public'   : null,
        ]);

        return $labels ? implode(', ', $labels) : 'Members';
    }

    /**
     * Get the user who created the announcement
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

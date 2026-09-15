<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'related_id',
        'related_type',
        'action_url',
        'is_read',
        'read_at',
        'priority',
        'expires_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related model (polymorphic)
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to get unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to get read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope to get non-expired notifications
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to order by priority and time
     */
    public function scopeOrderByPriority($query)
    {
        return $query->orderByRaw("
            CASE priority
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'normal' THEN 3
                WHEN 'low' THEN 4
            END
        ")->orderBy('created_at', 'desc');
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Get icon based on notification type
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'announcement' => 'fa-bullhorn',
            'loan_due' => 'fa-calendar-exclamation',
            'loan_approved' => 'fa-check-circle',
            'loan_rejected' => 'fa-times-circle',
            'loan_payment' => 'fa-money-bill',
            'security_alert' => 'fa-shield-exclamation',
            'email_verification' => 'fa-envelope-circle-check',
            'otp' => 'fa-key',
            'ticket_created' => 'fa-ticket-alt',
            'ticket_reply' => 'fa-comments',
            'ticket_status' => 'fa-circle-check',
            default => 'fa-bell',
        };
    }

    /**
     * Get color class based on priority
     */
    public function getColorClassAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'text-red-600 bg-red-50',
            'high' => 'text-orange-600 bg-orange-50',
            'normal' => 'text-blue-600 bg-blue-50',
            'low' => 'text-gray-600 bg-gray-50',
        };
    }

    /**
     * Get time ago format
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}

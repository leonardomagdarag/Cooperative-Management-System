<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoardMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'position',
        'title',
        'motto',
        'photo',
        'email',
        'phone',
        'bio',
        'term_start',
        'term_end',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'term_start' => 'date',
        'term_end' => 'date',
    ];

    /**
     * Get active board members ordered by position
     */
    public static function getActiveBoard()
    {
        return static::where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /**
     * Get board member by position
     */
    public static function getByPosition(string $position)
    {
        return static::where('position', $position)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Scope for active members
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

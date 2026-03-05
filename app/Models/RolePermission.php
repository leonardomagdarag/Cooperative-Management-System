<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role',
        'section',
        'permissions',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get all sections for a role
     */
    public static function getSectionsForRole(string $role)
    {
        return static::where('role', $role)
            ->where('is_active', true)
            ->pluck('section')
            ->toArray();
    }

    /**
     * Check if role has permission for section
     */
    public static function hasPermission(string $role, string $section, string $permission = 'view'): bool
    {
        $rolePermission = static::where('role', $role)
            ->where('section', $section)
            ->where('is_active', true)
            ->first();

        if (!$rolePermission) {
            return false;
        }

        return in_array($permission, $rolePermission->permissions ?? []);
    }
}

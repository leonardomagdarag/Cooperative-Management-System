<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfficerPermissionOverride;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class OfficerPermissionController extends Controller
{
    public function index(User $user): JsonResponse
    {
        $this->authorizeAccess($user);

        $overrides = $user->permissionOverrides
            ->pluck('effect', 'permission')
            ->all();

        return response()->json([
            'officer' => [
                'id' => $user->id,
                'name' => $this->displayName($user),
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
                'role_label' => PermissionCatalog::roleDisplayNames()[$user->getRoleNames()->first()] ?? ucfirst((string) $user->getRoleNames()->first()),
            ],
            'role_permissions' => $user->getPermissionsViaRoles()->pluck('name')->sort()->values()->all(),
            'overrides' => $overrides,
            'groups' => PermissionCatalog::groups(),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeAccess($user);

        $validated = $request->validate([
            'overrides' => 'required|array',
            'overrides.*' => ['required', 'in:'.OfficerPermissionOverride::EFFECT_ALLOW.','.OfficerPermissionOverride::EFFECT_DISALLOW],
        ]);

        $payload = collect($validated['overrides'])
            ->filter(fn ($effect, $permission) => in_array($permission, PermissionCatalog::allPermissions(), true)
                && in_array($effect, [OfficerPermissionOverride::EFFECT_ALLOW, OfficerPermissionOverride::EFFECT_DISALLOW], true))
            ->map(fn (string $effect, string $permission) => [
                'user_id' => $user->id,
                'permission' => $permission,
                'effect' => $effect,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($user, $payload): void {
            OfficerPermissionOverride::query()
                ->where('user_id', $user->id)
                ->delete();

            if ($payload !== []) {
                OfficerPermissionOverride::query()->insert($payload);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        OfficerPermissionOverride::forgetAllCachedEffects($user->id);

        return response()->json([
            'message' => 'Officer permission overrides saved.',
            'overrides' => collect($payload)->map(fn ($row) => [$row['permission'] => $row['effect']])->collapse()->all(),
        ]);
    }

    private function authorizeAccess(User $user): void
    {
        abort_unless(Auth::user()?->can('officers.manage-permissions'), 403);
        abort_unless($user->is_officer, 404);
    }

    private function displayName(User $user): string
    {
        $name = trim(collect([$user->personalData?->first_name, $user->personalData?->last_name])->filter()->implode(' '));

        return $name !== '' ? $name : $user->email;
    }
}

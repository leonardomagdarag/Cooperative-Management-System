<?php

namespace App\Http\Controllers;

use App\Models\TrustedDevice;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        $user->invalidateOtherSessions($request->session()->getId());

        $revokedDevices = TrustedDevice::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        $this->auditLogService->log(
            'security.password_changed',
            'User changed account password.',
            'security',
            $user,
            $user,
            [
                'trusted_devices_revoked' => (int) $revokedDevices,
                'other_sessions_invalidated' => true,
            ]
        );

        return redirect('/profile');
    }
}
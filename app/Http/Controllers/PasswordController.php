<?php

namespace App\Http\Controllers;

use App\Models\TrustedDevice;
use App\Services\AuditLogService;
use App\Support\PersonPasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => array_merge(PersonPasswordPolicy::passwordRules(false, PersonPasswordPolicy::normalizedNameTokens([
                $user->first_name,
                $user->mid_name,
                $user->last_name,
            ])), ['confirmed']),
        ]);

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
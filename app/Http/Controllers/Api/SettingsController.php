<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\SecurityOtpService;
use App\Support\PersonPasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => array_merge(PersonPasswordPolicy::passwordRules(false, PersonPasswordPolicy::normalizedNameTokens([
                $user->first_name,
                $user->mid_name,
                $user->last_name,
            ])), ['confirmed']),
            'otp_code' => ['required', 'string', 'digits:6'],
        ]);

        $securityOtp = app(SecurityOtpService::class);
        $verified = $securityOtp->verifyChallenge($user, $securityOtp->passwordChangePurpose(), $validated['otp_code']);
        if (! $verified) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        $user->password = $validated['password'];
        $user->remember_token = Str::random(60);
        $user->save();
        $user->invalidateOtherSessions(session()->getId());

        TrustedDevice::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        app(AuditLogService::class)->log(
            'member.security.password-changed',
            'Member changed their password from settings.',
            'auth',
            $user,
            $user,
        );

        return response()->json(['message' => 'Password updated. Trusted devices were revoked for safety.']);
    }

    public function sendPasswordChangeOtp(Request $request): JsonResponse
    {
        $user = Auth::user();

        $securityOtp = app(SecurityOtpService::class);
        $challenge = $securityOtp->issueChallenge($user, $securityOtp->passwordChangePurpose());
        $securityOtp->sendOtpMail($user, $securityOtp->passwordChangePurpose(), $challenge['otp_code']);

        return response()->json([
            'message' => 'A verification code was sent to your email.',
            'expires_at' => $challenge['challenge']->expires_at->toISOString(),
        ]);
    }

    public function revokeDevices(): JsonResponse
    {
        $user = Auth::user();

        TrustedDevice::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        $user->remember_token = Str::random(60);
        $user->save();

        app(AuditLogService::class)->log(
            'member.security.devices-revoked',
            'Member revoked trusted devices from settings.',
            'auth',
            $user,
            $user,
        );

        return response()->json(['message' => 'Trusted devices revoked.']);
    }

    public function deactivateAccount(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $user = Auth::user();
        $user->account_status = 'inactive';
        $user->save();

        TrustedDevice::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        app(AuditLogService::class)->log(
            'member.account.deactivated',
            'Member deactivated their account from settings.',
            'auth',
            $user,
            $user,
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Account deactivated.']);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $user = Auth::user();

        app(AuditLogService::class)->log(
            'member.account.deleted',
            'Member deleted their account from settings.',
            'auth',
            $user,
            $user,
        );

        $user->delete();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Account deleted.']);
    }
}

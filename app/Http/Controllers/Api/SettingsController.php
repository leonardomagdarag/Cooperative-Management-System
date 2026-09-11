<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\SecurityOtpService;
use App\Support\PersonPasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

    public function getServiceAvailability(): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.view'), 403);

        $services = ['deposit', 'withdrawal', 'loan_application', 'loan_payment'];
        $result = [];

        foreach ($services as $service) {
            $base = [
                'global' => (bool) SystemSetting::get("service_{$service}_enabled", true),
                'member' => (bool) SystemSetting::get("service_{$service}_member_enabled", true),
                'officer' => (bool) SystemSetting::get("service_{$service}_officer_enabled", true),
            ];

            if (in_array($service, ['deposit', 'withdrawal', 'loan_payment'], true)) {
                $base['counter'] = (bool) SystemSetting::get("service_{$service}_counter_enabled", true);
                $base['online'] = (bool) SystemSetting::get("service_{$service}_online_enabled", true);
                if ($service === 'withdrawal') {
                    $base['bank_transfer'] = (bool) SystemSetting::get('service_withdrawal_bank_transfer_enabled', true);
                }
            }

            $result[$service] = $base;
        }

        // Registration settings
        $result['registration'] = [
            'global' => (bool) SystemSetting::get('service_registration_enabled', true),
            'member' => (bool) SystemSetting::get('service_registration_member_enabled', true),
        ];

        // Login settings
        $result['login'] = [
            'global' => (bool) SystemSetting::get('service_login_enabled', true),
            'member' => (bool) SystemSetting::get('service_login_member_enabled', true),
            'officer' => (bool) SystemSetting::get('service_login_officer_enabled', true),
        ];

        return response()->json(['data' => $result]);
    }

    public function updateServiceAvailability(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $validated = $request->validate([
            'services' => 'required|array',
            'services.*.global' => 'boolean',
            'services.*.member' => 'boolean',
            'services.*.officer' => 'boolean',
            'services.*.counter' => 'sometimes|boolean',
            'services.*.online' => 'sometimes|boolean',
            'services.*.bank_transfer' => 'sometimes|boolean',
            'notify_affected' => 'boolean',
        ]);

        $allowedServices = ['deposit', 'withdrawal', 'loan_application', 'loan_payment', 'registration', 'login'];
        $unknownServices = array_diff(array_keys($validated['services']), $allowedServices);

        if ($unknownServices !== []) {
            return response()->json(['message' => 'One or more services are invalid.'], 422);
        }

        $newlyUnavailable = [];

        foreach ($validated['services'] as $service => $flags) {
            if ($service === 'registration') {
                $settings = [
                    'global' => 'service_registration_enabled',
                    'member' => 'service_registration_member_enabled',
                ];
            } elseif ($service === 'login') {
                $settings = [
                    'global' => 'service_login_enabled',
                    'member' => 'service_login_member_enabled',
                    'officer' => 'service_login_officer_enabled',
                ];
            } else {
                $settings = [
                    'global' => "service_{$service}_enabled",
                    'member' => "service_{$service}_member_enabled",
                    'officer' => "service_{$service}_officer_enabled",
                ];

                if (in_array($service, ['deposit', 'withdrawal', 'loan_payment'], true)) {
                    $settings['counter'] = "service_{$service}_counter_enabled";
                    $settings['online'] = "service_{$service}_online_enabled";
                    if ($service === 'withdrawal') {
                        $settings['bank_transfer'] = 'service_withdrawal_bank_transfer_enabled';
                    }
                }
            }

            foreach ($settings as $flag => $key) {
                $nextValue = (bool) ($flags[$flag] ?? true);
                $previousValue = (bool) SystemSetting::get($key, true);

                if ($previousValue && ! $nextValue) {
                    $newlyUnavailable[$service][$flag] = false;
                }

                SystemSetting::set($key, $nextValue);
            }
        }

        if (($validated['notify_affected'] ?? false) && $newlyUnavailable !== []) {
            $this->notifyAffectedUsers($newlyUnavailable);
        }

        return response()->json(['message' => 'Service availability updated.']);
    }

    private function notifyAffectedUsers(array $services): void
    {
        $notificationService = app(NotificationService::class);

        foreach ($services as $service => $flags) {
            if ($service === 'registration') {
                if (isset($flags['global']) && $flags['global'] === false) {
                    $users = User::role('admin')->where('account_status', 'active')->get();
                    foreach ($users as $user) {
                        $notificationService->notifyAccountActivity(
                            $user,
                            'Service Unavailable',
                            'Public member registration has been disabled.'
                        );
                    }
                }

                continue;
            }

            if ($service === 'login') {
                if (isset($flags['global']) && $flags['global'] === false) {
                    $users = User::where('account_status', 'active')->get();
                    foreach ($users as $user) {
                        $notificationService->notifyAccountActivity(
                            $user,
                            'Service Unavailable',
                            'Public login access has been disabled.'
                        );
                    }

                    continue;
                }

                if (isset($flags['member']) && $flags['member'] === false) {
                    $users = User::role('member')->where('account_status', 'active')->get();
                    foreach ($users as $user) {
                        $notificationService->notifyAccountActivity(
                            $user,
                            'Service Unavailable',
                            'Member login is temporarily unavailable due to maintenance.'
                        );
                    }
                }

                if (isset($flags['officer']) && $flags['officer'] === false) {
                    $officerRoles = ['admin', 'manager', 'accountant', 'loan', 'secretary'];
                    $users = User::whereHas('roles', fn ($q) => $q->whereIn('name', $officerRoles))
                        ->where('account_status', 'active')
                        ->get();
                    foreach ($users as $user) {
                        $notificationService->notifyAccountActivity(
                            $user,
                            'Service Unavailable',
                            'Officer login is temporarily unavailable due to maintenance.'
                        );
                    }
                }

                continue;
            }

            $serviceLabel = ucfirst(str_replace('_', ' ', $service));

            // Global OFF - notify all active users
            if (isset($flags['global']) && $flags['global'] === false) {
                $users = User::where('account_status', 'active')->get();
                foreach ($users as $user) {
                    $notificationService->notifyAccountActivity(
                        $user,
                        'Service Unavailable',
                        "{$serviceLabel} is temporarily unavailable due to maintenance."
                    );
                }

                continue;
            }

            // Member OFF - notify active members
            if (isset($flags['member']) && $flags['member'] === false) {
                $users = User::role('member')->where('account_status', 'active')->get();
                foreach ($users as $user) {
                    $notificationService->notifyAccountActivity(
                        $user,
                        'Service Unavailable',
                        "{$serviceLabel} is temporarily unavailable for members due to maintenance."
                    );
                }
            }

            // Officer OFF - notify active officers
            if (isset($flags['officer']) && $flags['officer'] === false) {
                $officerRoles = ['admin', 'manager', 'accountant', 'loan', 'secretary'];
                $users = User::whereHas('roles', fn ($q) => $q->whereIn('name', $officerRoles))
                    ->where('account_status', 'active')
                    ->get();
                foreach ($users as $user) {
                    $notificationService->notifyAccountActivity(
                        $user,
                        'Service Unavailable',
                        "{$serviceLabel} is temporarily unavailable for officers due to maintenance."
                    );
                }
            }

            // Transaction mode specific notifications
            if (in_array($service, ['deposit', 'withdrawal', 'loan_payment'], true)) {
                $modeLabels = [
                    'counter' => 'Counter (OTC)',
                    'online' => 'Online Payment',
                    'bank_transfer' => 'Bank Transfer',
                ];

                foreach (['counter', 'online', 'bank_transfer'] as $mode) {
                    if (! isset($flags[$mode]) || $flags[$mode] !== false) {
                        continue;
                    }
                    if ($mode === 'bank_transfer' && $service !== 'withdrawal') {
                        continue;
                    }

                    $modeLabel = $modeLabels[$mode] ?? $mode;
                    $users = User::where('account_status', 'active')->get();
                    foreach ($users as $user) {
                        $notificationService->notifyAccountActivity(
                            $user,
                            'Service Unavailable',
                            "{$serviceLabel} - {$modeLabel} is temporarily unavailable due to maintenance."
                        );
                    }
                }
            }
        }
    }
}

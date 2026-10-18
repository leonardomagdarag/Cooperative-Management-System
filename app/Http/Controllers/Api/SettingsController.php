<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EmailConfigurationTestMail;
use App\Models\SystemSetting;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CountersignService;
use App\Services\NotificationService;
use App\Services\SecurityOtpService;
use App\Services\TextBeeService;
use App\Support\FinancialSettings;
use App\Support\PersonPasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

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
        abort_unless(Auth::user()?->can('settings.update'), 403);

        // Withdrawal is intentionally absent: it now lives in the Financial
        // Accounts settings tab, which manages its own limits and toggles.
        $services = ['deposit', 'loan_application', 'loan_payment'];
        $result = [];

        foreach ($services as $service) {
            $base = [
                'global' => (bool) SystemSetting::get("service_{$service}_enabled", true),
                'member' => (bool) SystemSetting::get("service_{$service}_member_enabled", true),
                'officer' => (bool) SystemSetting::get("service_{$service}_officer_enabled", true),
            ];

            if (in_array($service, ['deposit', 'loan_payment'], true)) {
                $base['counter'] = (bool) SystemSetting::get("service_{$service}_counter_enabled", true);
                $base['online'] = (bool) SystemSetting::get("service_{$service}_online_enabled", true);
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

        // Withdrawal is no longer managed here — see updateFinancialSettings().
        $allowedServices = ['deposit', 'loan_application', 'loan_payment', 'registration', 'login'];
        $unknownServices = array_diff(array_keys($validated['services']), $allowedServices);

        if ($unknownServices !== []) {
            return response()->json(['message' => 'One or more services are invalid.'], 422);
        }

        $newlyUnavailable = [];
        $flatSettings = [];

        foreach ($validated['services'] as $service => $flags) {
            if ($service === 'registration') {
                $registrationEnabled = (bool) ($flags['global'] ?? $flags['member'] ?? true);
                $flatSettings['service_registration_enabled'] = (bool) ($flags['global'] ?? $registrationEnabled);
                $flatSettings['service_registration_member_enabled'] = (bool) ($flags['member'] ?? $registrationEnabled);

                continue;
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

                if (in_array($service, ['deposit', 'loan_payment'], true)) {
                    $settings['counter'] = "service_{$service}_counter_enabled";
                    $settings['online'] = "service_{$service}_online_enabled";
                }
            }

            foreach ($settings as $flag => $key) {
                $nextValue = (bool) ($flags[$flag] ?? true);
                $flatSettings[$key] = $nextValue;
            }
        }

        $countersign = app(CountersignService::class);
        $pending = $countersign->request('settings.update', [
            'settings' => $flatSettings,
            'services' => $validated['services'],
            'notify_affected' => $validated['notify_affected'] ?? false,
            'sync_login' => true,
        ], Auth::user());

        app(AuditLogService::class)->log(
            'settings.service_availability_requested',
            'Service availability change requested and queued for countersign approval.',
            'system_setting',
            null,
            Auth::user(),
            ['request_id' => $pending->id, 'services' => $validated['services']],
        );

        return response()->json([
            'message' => 'Service availability update requested and requires countersign approval.',
            'request_id' => $pending->id,
            'status' => 'pending',
        ], 202);
    }

    public function getFinancialSettings(): JsonResponse
    {
        abort_unless(Auth::user()?->can('financial_settings.view'), 403);

        return response()->json(['data' => $this->financialSettingsPayload()]);
    }

    public function updateFinancialSettings(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('financial_settings.update'), 403);

        $validated = $request->validate([
            'savings.rate' => 'required|numeric|min:0|max:100',
            'savings.interval' => ['required', Rule::in(FinancialSettings::INTERVALS)],
            'savings.min_balance' => 'required|numeric|min:0',
            'share_capital.rate' => 'required|numeric|min:0|max:100',
            'share_capital.interval' => ['required', Rule::in(FinancialSettings::INTERVALS)],
            'share_capital.min_balance' => 'required|numeric|min:0',
            'interest.min_payout' => 'required|numeric|min:0',
            'interest.notify_members' => 'required|boolean',
            'withdrawal.min_amount' => 'required|numeric|min:0',
            'withdrawal.max_amount' => 'required|numeric|min:0',
            'withdrawal.savings_enabled' => 'required|boolean',
            'withdrawal.share_capital_enabled' => 'required|boolean',
            'withdrawal.enabled' => 'required|boolean',
            'withdrawal.member' => 'required|boolean',
            'withdrawal.officer' => 'required|boolean',
            'withdrawal.counter' => 'required|boolean',
            'withdrawal.online' => 'required|boolean',
            'withdrawal.bank_transfer' => 'required|boolean',
            'notify_affected' => 'boolean',
        ]);

        if ((float) $validated['withdrawal']['max_amount'] > 0
            && (float) $validated['withdrawal']['min_amount'] > (float) $validated['withdrawal']['max_amount']) {
            return response()->json(['message' => 'Minimum withdrawal cannot exceed the maximum withdrawal.'], 422);
        }

        $flatSettings = [
            'financial_savings_interest_rate' => (float) $validated['savings']['rate'],
            'financial_savings_interest_interval' => $validated['savings']['interval'],
            'financial_savings_min_balance_to_earn' => (float) $validated['savings']['min_balance'],
            'financial_share_capital_interest_rate' => (float) $validated['share_capital']['rate'],
            'financial_share_capital_interest_interval' => $validated['share_capital']['interval'],
            'financial_share_capital_min_balance_to_earn' => (float) $validated['share_capital']['min_balance'],
            'financial_interest_min_payout' => (float) $validated['interest']['min_payout'],
            'financial_interest_notify_members' => (bool) $validated['interest']['notify_members'],
            'financial_withdrawal_min_amount' => (float) $validated['withdrawal']['min_amount'],
            'financial_withdrawal_max_amount' => (float) $validated['withdrawal']['max_amount'],
            'financial_withdrawal_savings_enabled' => (bool) $validated['withdrawal']['savings_enabled'],
            'financial_withdrawal_share_capital_enabled' => (bool) $validated['withdrawal']['share_capital_enabled'],
            'service_withdrawal_enabled' => (bool) $validated['withdrawal']['enabled'],
            'service_withdrawal_member_enabled' => (bool) $validated['withdrawal']['member'],
            'service_withdrawal_officer_enabled' => (bool) $validated['withdrawal']['officer'],
            'service_withdrawal_counter_enabled' => (bool) $validated['withdrawal']['counter'],
            'service_withdrawal_online_enabled' => (bool) $validated['withdrawal']['online'],
            'service_withdrawal_bank_transfer_enabled' => (bool) $validated['withdrawal']['bank_transfer'],
        ];

        // Only the withdrawal service is affected by this tab, so its flag tree
        // is what notifyAffectedUsers() needs — and only when asked.
        $services = [
            'withdrawal' => [
                'global' => (bool) $validated['withdrawal']['enabled'],
                'member' => (bool) $validated['withdrawal']['member'],
                'officer' => (bool) $validated['withdrawal']['officer'],
                'counter' => (bool) $validated['withdrawal']['counter'],
                'online' => (bool) $validated['withdrawal']['online'],
                'bank_transfer' => (bool) $validated['withdrawal']['bank_transfer'],
            ],
        ];

        $countersign = app(CountersignService::class);
        $pending = $countersign->request('financial.settings.update', [
            'settings' => $flatSettings,
            'services' => $services,
            'notify_affected' => $validated['notify_affected'] ?? false,
        ], Auth::user());

        app(AuditLogService::class)->log(
            'financial.settings.requested',
            'Financial account settings change requested and queued for countersign approval.',
            'financial',
            null,
            Auth::user(),
            ['request_id' => $pending->id, 'keys' => array_keys($flatSettings)],
        );

        return response()->json([
            'message' => 'Financial account settings update requested and requires countersign approval.',
            'request_id' => $pending->id,
            'status' => 'pending',
        ], 202);
    }

    public function runInterestNow(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('interest.run'), 403);

        $validated = $request->validate([
            'account_type' => ['required', Rule::in(FinancialSettings::ACCOUNT_TYPES)],
        ]);

        if (FinancialSettings::rate($validated['account_type']) <= 0) {
            return response()->json(['message' => 'Set an interest rate before running an interest batch.'], 422);
        }

        $countersign = app(CountersignService::class);
        $pending = $countersign->request('financial.interest.run', [
            'account_type' => $validated['account_type'],
            'requested_by' => Auth::id(),
        ], Auth::user());

        app(AuditLogService::class)->log(
            'financial.interest.manual_requested',
            'Manual interest run requested and queued for countersign approval.',
            'financial',
            null,
            Auth::user(),
            ['request_id' => $pending->id, 'account_type' => $validated['account_type']],
        );

        return response()->json([
            'message' => 'Interest run requested and requires countersign approval.',
            'request_id' => $pending->id,
            'status' => 'pending',
        ], 202);
    }

    /**
     * Shape the financial settings for the SPA. Kept in one place so the read
     * and write endpoints cannot drift.
     */
    private function financialSettingsPayload(): array
    {
        return [
            'savings' => [
                'rate' => FinancialSettings::rate('savings'),
                'interval' => FinancialSettings::interval('savings'),
                'min_balance' => FinancialSettings::minBalance('savings'),
            ],
            'share_capital' => [
                'rate' => FinancialSettings::rate('share-capital'),
                'interval' => FinancialSettings::interval('share-capital'),
                'min_balance' => FinancialSettings::minBalance('share-capital'),
            ],
            'interest' => [
                'min_payout' => FinancialSettings::minPayout(),
                'notify_members' => FinancialSettings::notifyMembers(),
            ],
            'withdrawal' => [
                'min_amount' => FinancialSettings::withdrawalMin(),
                'max_amount' => FinancialSettings::withdrawalMax(),
                'savings_enabled' => FinancialSettings::savingsWithdrawalEnabled(),
                'share_capital_enabled' => FinancialSettings::shareCapitalWithdrawalEnabled(),
                'enabled' => (bool) SystemSetting::get('service_withdrawal_enabled', true),
                'member' => (bool) SystemSetting::get('service_withdrawal_member_enabled', true),
                'officer' => (bool) SystemSetting::get('service_withdrawal_officer_enabled', true),
                'counter' => (bool) SystemSetting::get('service_withdrawal_counter_enabled', true),
                'online' => (bool) SystemSetting::get('service_withdrawal_online_enabled', true),
                'bank_transfer' => (bool) SystemSetting::get('service_withdrawal_bank_transfer_enabled', true),
            ],
            'last_credited' => [
                'savings' => FinancialSettings::lastCreditedAt('savings')?->toDateTimeString(),
                'share_capital' => FinancialSettings::lastCreditedAt('share-capital')?->toDateTimeString(),
            ],
        ];
    }

    public function notifyAffectedUsers(array $services): void
    {
        $notificationService = app(NotificationService::class);

        foreach ($services as $service => $flags) {
            if ($service === 'registration') {
                if (isset($flags['global']) && $flags['global'] === false) {
                    $users = User::role(['admin', 'chairman'])->where('account_status', 'active')->get();
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
                    $officerRoles = ['admin', 'chairman', 'manager', 'accountant', 'loan'];
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
                $officerRoles = ['admin', 'chairman', 'manager', 'accountant', 'loan'];
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

    public function getSmsSettings(): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        return response()->json([
            'data' => [
                'sms_service_enabled' => (bool) SystemSetting::get('sms_service_enabled', false),
                'textbee_api_key_configured' => app(TextBeeService::class)->isConfigured(),
                'textbee_device_id' => (string) SystemSetting::get('textbee_device_id', ''),
            ],
        ]);
    }

    public function updateSmsSettings(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $validated = $request->validate([
            'sms_service_enabled' => 'boolean',
            'textbee_api_key' => 'nullable|string|max:255',
            'textbee_device_id' => 'nullable|string',
        ]);

        if (array_key_exists('sms_service_enabled', $validated)) {
            SystemSetting::set('sms_service_enabled', $validated['sms_service_enabled']);
        }
        if (array_key_exists('textbee_api_key', $validated)) {
            SystemSetting::set('textbee_api_key', $validated['textbee_api_key'] ?? '');
        }
        if (array_key_exists('textbee_device_id', $validated)) {
            SystemSetting::set('textbee_device_id', $validated['textbee_device_id'] ?? '');
        }

        app(AuditLogService::class)->log(
            'settings.sms_updated',
            'SMS integration settings updated.',
            'system_setting',
            null,
            Auth::user(),
            [
                'sms_service_enabled' => $validated['sms_service_enabled'] ?? null,
                'textbee_device_id' => array_key_exists('textbee_device_id', $validated) ? $validated['textbee_device_id'] : null,
                'api_key_changed' => array_key_exists('textbee_api_key', $validated),
            ],
        );

        return response()->json(['message' => 'SMS settings updated.']);
    }

    public function testSms(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
        ]);

        $phone = $validated['phone_number'];

        $sent = app(TextBeeService::class)->send(
            $phone,
            'Settlers CC: TextBee test message. SMS service is working correctly.'
        );

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'Test SMS sent.' : 'SMS failed. Check TextBee configuration.',
        ]);
    }

    public function testEmail(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            Mail::to($validated['email'])->send(new EmailConfigurationTestMail);

            return response()->json([
                'success' => true,
                'message' => 'Test email sent.',
            ]);
        } catch (Throwable $e) {
            Log::error('Email configuration test failed.', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test email failed. Check the mail configuration and server logs.',
            ], 502);
        }
    }
}

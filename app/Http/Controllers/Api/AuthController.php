<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginHandoff;
use App\Models\OfficerPermissionOverride;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\LoginHandoffService;
use App\Services\NotificationService;
use App\Services\SecurityOtpService;
use App\Services\TurnstileService;
use App\Support\MaintenanceState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly SecurityOtpService $securityOtpService,
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {}

    public function login(Request $request, TurnstileService $turnstileService): JsonResponse
    {
        if (! app()->runningUnitTests() && ! $turnstileService->verify(
            $request->input('cf-turnstile-response'),
            $request->ip()
        )) {
            return response()->json(['message' => 'Captcha verification failed. Please try again.'], 403);
        }

        $request->validate([
            'email' => 'required|string',
            'password' => 'required',
        ]);

        $loginId = $request->input('email');
        $emailNorm = User::normalizeEmail($loginId);

        $user = User::query()
            ->where(function ($q) use ($emailNorm, $loginId) {
                $q->whereRaw('LOWER(TRIM(users.email)) = ?', [$emailNorm]);

                $cleanPhone = preg_replace('/[^0-9\+]/', '', $loginId);
                if (strlen($cleanPhone) >= 10) {
                    $q->orWhereHas('personalData.contact', function ($contactQuery) use ($cleanPhone) {
                        $contactQuery->where('phone_no', $cleanPhone)
                            ->whereNotNull('phone_verified_at');
                    });
                }
            })
            ->get()
            ->first(fn (User $candidate) => Hash::check($request->password, $candidate->password));

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $isOfficer = $user->is_officer;
        $audience = $isOfficer ? MaintenanceState::AUDIENCE_OFFICER : MaintenanceState::AUDIENCE_MEMBER;

        // The global switch is checked here as well as in
        // EnsurePublicLoginEnabled: /api/login carries only ['web', 'throttle'],
        // so the middleware never runs for it and the global switch would
        // otherwise be advisory rather than enforced.
        $loginAllowed = (bool) SystemSetting::get('service_login_enabled', true)
            && (bool) SystemSetting::get("service_login_{$audience}_enabled", true);

        if (! $loginAllowed) {
            MaintenanceState::enterLoginUnavailable($audience);

            $this->auditLogService->log(
                'auth.login_blocked',
                'Login blocked because login is disabled for this role.',
                'auth',
                $user,
                $user,
                ['user_type' => $audience]
            );

            return response()->json([
                'message' => 'Login is currently unavailable for your account. Please try again later.',
                'maintenance' => true,
                'reason' => MaintenanceState::REASON_LOGIN,
                'audience' => $audience,
                'redirect' => '/maintenance',
            ], 403);
        }

        if (($user->account_status ?? 'active') !== 'active') {
            Auth::guard('web')->logout();

            return response()->json(['message' => 'Your account is not active.'], 403);
        }

        if ($user->must_set_password) {
            return response()->json(['message' => 'Please set your password using the invitation sent to your email.'], 403);
        }

        $previousSessionId = $request->session()->getId();
        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $handoff = app(LoginHandoffService::class)->requestApproval($user, $previousSessionId, $request);
        if ($handoff) {

            $this->auditLogService->log('auth.login_approval_requested', 'A sign-in is waiting for approval from the active device.', 'auth', $handoff, $user, [
                'device' => $handoff->device_label,
                'ip_address' => $handoff->ip_address,
            ]);

            $request->session()->put('security.pending_login_handoff', $handoff->id);

            return response()->json([
                'requires_approval' => true,
                'message' => 'Approve this sign-in from your currently active device. This request expires in 5 minutes.',
            ]);
        }

        $fingerprint = $this->securityOtpService->buildDeviceFingerprint($request);
        $redirect = $request->session()->pull('url.intended', route('dashboard', absolute: false));
        $isNewDevice = ! $this->securityOtpService->isTrustedDevice($user, $fingerprint);
        $otpBypassed = ! $this->securityOtpService->shouldEnforceNewDeviceOtp();

        if (! $otpBypassed && $isNewDevice) {
            $purpose = $this->securityOtpService->newDevicePurpose();
            $issued = $this->securityOtpService->issueChallenge($user, $purpose, ['fingerprint' => $fingerprint]);
            $this->securityOtpService->sendOtpMail($user, $purpose, $issued['otp_code']);

            $request->session()->put([
                'security.pending_otp' => true,
                'security.pending_otp_purpose' => $purpose,
                'security.pending_otp_fingerprint' => $fingerprint,
                'security.intended_url' => $redirect,
            ]);
            $this->securityOtpService->stashDebugCode($request->session(), $issued['otp_code']);

            return response()->json([
                'requires_otp' => true,
                'redirect' => route('otp.challenge.show'),
                'message' => 'Security verification is required for this device.',
            ]);
        }

        $this->securityOtpService->trustDevice($user, $fingerprint, $request->userAgent(), $request->ip(), $request->session()->getId());
        $user->activateSession($request->session()->getId());
        $user->invalidateOtherSessions($request->session()->getId());
        if ($isNewDevice) {
            $this->notificationService->notifyNewDeviceLogin($user, $request->ip(), $otpBypassed ? 'bypassed' : 'trusted');
        }
        $this->auditLogService->log('auth.login_success', 'User logged in successfully.', 'auth', $user, $user, [
            'role' => $user->primary_role,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'user' => $this->formatUser($user),
            'redirect' => $redirect,
        ]);
    }

    public function loginHandoffs(Request $request): JsonResponse
    {
        $user = $request->user('web');
        $sessionId = $request->session()->getId();
        $handoff = LoginHandoff::query()->where('user_id', $user->id)
            ->where('current_session_id', $sessionId)->whereIn('status', ['pending', 'approved'])
            ->where('expires_at', '>', now())->latest('id')->first();

        return response()->json(['request' => $handoff ? [
            'id' => $handoff->id,
            'device' => $handoff->device_label,
            'ip_address' => $handoff->ip_address,
            'created_at' => $handoff->created_at,
            'expires_at' => $handoff->expires_at,
            'status' => $handoff->status,
        ] : null]);
    }

    public function decideLoginHandoff(Request $request, LoginHandoff $loginHandoff): JsonResponse
    {
        $request->validate(['decision' => 'required|in:approve,reject']);
        $updated = DB::transaction(function () use ($request, $loginHandoff) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user('web')->id);
            $updated = LoginHandoff::query()->whereKey($loginHandoff->id)
                ->where('user_id', $user->id)
                ->where('current_session_id', $request->session()->getId())
                ->where('status', 'pending')->where('expires_at', '>', now())
                ->update(['status' => $request->input('decision') === 'approve' ? 'approved' : 'rejected', 'decided_at' => now()]);

            if ($updated && $request->input('decision') === 'approve') {
                LoginHandoff::query()->where('user_id', $user->id)->where('current_session_id', $request->session()->getId())
                    ->where('id', '!=', $loginHandoff->id)->whereIn('status', ['pending', 'approved'])
                    ->update(['status' => 'rejected', 'decided_at' => now()]);
            }

            return $updated;
        });

        abort_unless($updated, 409, 'This sign-in request has expired or was already handled.');

        $this->auditLogService->log(
            $request->input('decision') === 'approve' ? 'auth.login_approved' : 'auth.login_rejected',
            $request->input('decision') === 'approve' ? 'A pending sign-in was approved from the active device.' : 'A pending sign-in was rejected from the active device.',
            'auth',
            $loginHandoff,
            $request->user('web'),
            ['device' => $loginHandoff->device_label, 'ip_address' => $loginHandoff->ip_address]
        );

        return response()->json(['status' => $request->input('decision') === 'approve' ? 'approved' : 'rejected']);
    }

    public function loginHandoffStatus(Request $request): JsonResponse
    {
        $handoffId = $request->session()->get('security.pending_login_handoff');
        $handoff = LoginHandoff::query()->whereKey($handoffId)
            ->where('user_id', $request->user('web')->id)
            ->where('pending_session_id', $request->session()->getId())->first();

        if (! $handoff || $handoff->expires_at->isPast()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['status' => 'expired']);
        }

        if (in_array($handoff->status, ['rejected', 'expired'], true)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $status = $request->session()->get('security.login_handoff_otp_started')
            ? 'otp_pending'
            : $handoff->status;
        if ($status === 'otp_bypassed') {
            $status = 'approved';
        }

        return response()->json([
            'status' => $status,
            'email_otp_bypassed' => ! $this->securityOtpService->shouldEnforceNewDeviceOtp(),
        ]);
    }

    public function requestLoginHandoffOtp(Request $request): JsonResponse
    {
        $user = $request->user('web');
        abort_if($request->session()->get('security.login_handoff_otp_started'), 409, 'A verification code has already been sent for this sign-in.');

        if (! $this->securityOtpService->shouldEnforceNewDeviceOtp()) {
            $handoff = DB::transaction(function () use ($request, $user) {
                $handoff = LoginHandoff::query()
                    ->whereKey($request->session()->get('security.pending_login_handoff'))
                    ->where('user_id', $user->id)
                    ->where('pending_session_id', $request->session()->getId())
                    ->where('status', 'pending')
                    ->where('expires_at', '>', now())
                    ->lockForUpdate()
                    ->first();

                abort_unless($handoff, 409, 'This sign-in request has expired or was already handled. Please sign in again.');
                $handoff->update(['status' => 'otp_bypassed']);

                return $handoff;
            });

            $this->auditLogService->log(
                'auth.login_otp_bypassed',
                'New-device email verification was bypassed by the configured login OTP setting.',
                'auth',
                $handoff,
                $user,
                ['device' => $handoff->device_label, 'ip_address' => $handoff->ip_address]
            );

            return $this->completeLoginHandoff($request);
        }

        $otpRequest = DB::transaction(function () use ($request, $user) {
            $handoff = LoginHandoff::query()
                ->whereKey($request->session()->get('security.pending_login_handoff'))
                ->where('user_id', $user->id)
                ->where('pending_session_id', $request->session()->getId())
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            abort_unless($handoff, 409, 'This sign-in request has expired or was already handled. Please sign in again.');

            $fingerprint = $this->securityOtpService->buildDeviceFingerprint($request);
            $purpose = $this->securityOtpService->newDevicePurpose();
            $issued = $this->securityOtpService->issueChallenge($user, $purpose, [
                'fingerprint' => $fingerprint,
                'login_handoff_id' => $handoff->id,
            ]);
            $this->securityOtpService->sendOtpMail($user, $purpose, $issued['otp_code']);

            $otpExpiryMinutes = max(1, (int) SystemSetting::get('security_otp_expiry_minutes', 10));
            $handoff->update(['status' => 'otp_pending', 'expires_at' => now()->addMinutes($otpExpiryMinutes)]);

            return [
                'handoff' => $handoff,
                'fingerprint' => $fingerprint,
                'otp_code' => $issued['otp_code'],
            ];
        });
        $handoff = $otpRequest['handoff'];
        $request->session()->put([
            'security.pending_otp' => true,
            'security.pending_otp_purpose' => $this->securityOtpService->newDevicePurpose(),
            'security.pending_otp_fingerprint' => $otpRequest['fingerprint'],
            'security.intended_url' => route('dashboard', absolute: false),
            'security.login_handoff_otp_started' => true,
        ]);
        $this->securityOtpService->stashDebugCode($request->session(), $otpRequest['otp_code']);

        $this->auditLogService->log(
            'auth.login_otp_requested',
            'Email verification was requested as an alternative to active-device sign-in approval.',
            'auth',
            $handoff,
            $user,
            ['device' => $handoff->device_label, 'ip_address' => $handoff->ip_address]
        );

        return response()->json([
            'requires_otp' => true,
            'redirect' => route('otp.challenge.show'),
            'message' => 'A verification code was sent to your registered email address.',
        ]);
    }

    public function completeLoginHandoff(Request $request): JsonResponse
    {
        $handoffId = $request->session()->get('security.pending_login_handoff');
        $handoff = LoginHandoff::query()->whereKey($handoffId)->where('user_id', $request->user('web')->id)
            ->where('pending_session_id', $request->session()->getId())->whereIn('status', ['approved', 'otp_bypassed'])
            ->where('expires_at', '>', now())->first();
        abort_unless($handoff, 403, 'This sign-in was not approved or has expired.');
        abort_if($request->session()->get('security.login_handoff_otp_started'), 409, 'Security verification has already started.');

        $user = $request->user('web');
        $fingerprint = $this->securityOtpService->buildDeviceFingerprint($request);
        $isNewDevice = ! $this->securityOtpService->isTrustedDevice($user, $fingerprint);
        if ($isNewDevice && $this->securityOtpService->shouldEnforceNewDeviceOtp()) {
            $purpose = $this->securityOtpService->newDevicePurpose();
            $issued = $this->securityOtpService->issueChallenge($user, $purpose, [
                'fingerprint' => $fingerprint,
                'login_handoff_id' => $handoff->id,
            ]);
            $this->securityOtpService->sendOtpMail($user, $purpose, $issued['otp_code']);
            $otpExpiryMinutes = max(1, (int) SystemSetting::get('security_otp_expiry_minutes', 10));
            $handoff->update(['expires_at' => now()->addMinutes($otpExpiryMinutes)]);
            $request->session()->put(['security.pending_otp' => true, 'security.pending_otp_purpose' => $purpose, 'security.pending_otp_fingerprint' => $fingerprint]);
            $request->session()->put('security.login_handoff_otp_started', true);
            $this->securityOtpService->stashDebugCode($request->session(), $issued['otp_code']);

            return response()->json(['requires_otp' => true, 'redirect' => route('otp.challenge.show')]);
        }

        $completed = LoginHandoff::query()
            ->whereKey($handoff->id)
            ->where('user_id', $user->id)
            ->where('pending_session_id', $request->session()->getId())
            ->whereIn('status', ['approved', 'otp_bypassed'])
            ->where('expires_at', '>', now())
            ->update(['status' => 'completed', 'decided_at' => now()]);
        abort_unless($completed, 409, 'This sign-in was already completed or has expired.');
        $request->session()->forget('security.login_handoff_otp_started');
        $this->securityOtpService->trustDevice($user, $fingerprint, $request->userAgent(), $request->ip(), $request->session()->getId());
        $user->activateSession($request->session()->getId());
        $user->invalidateOtherSessions($request->session()->getId());
        $this->notificationService->notifyNewDeviceLogin($user, $request->ip(), 'approved');
        $this->auditLogService->log('auth.login_success', 'User completed a device sign-in.', 'auth', $handoff, $user, [
            'role' => $user->primary_role,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['success' => true, 'user' => $this->formatUser($user), 'redirect' => route('dashboard', absolute: false)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user('web');
        $this->auditLogService->log('auth.logout', 'User logged out.', 'auth', $user, $user);

        $user?->clearCurrentSession($request->session()->getId());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user('web');

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email is already verified.']);
        }

        $this->notificationService->sendEmailVerification($user);

        return response()->json(['message' => 'A verification email has been sent to your email address.']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user('web');

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($this->formatUser($user));
    }

    private function formatUser($user): array
    {
        $user->loadMissing('personalData');

        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        if ($user->is_officer) {
            $overrides = OfficerPermissionOverride::query()
                ->where('user_id', $user->id)
                ->pluck('effect', 'permission');

            if ($overrides->isNotEmpty()) {
                $allowed = $overrides->filter(fn ($effect) => $effect === OfficerPermissionOverride::EFFECT_ALLOW)->keys()->all();
                $disallowed = $overrides->filter(fn ($effect) => $effect === OfficerPermissionOverride::EFFECT_DISALLOW)->keys()->all();

                $permissions = array_values(array_diff(array_unique(array_merge($permissions, $allowed)), $disallowed));
            }
        }

        if (! $user->hasRole('chairman')) {
            $permissions = array_values(array_diff($permissions, ['oversight.approve']));
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => (bool) $user->hasVerifiedEmail(),
            'primary_role' => $user->primary_role,
            'is_officer' => (bool) $user->is_officer,
            'account_status' => $user->account_status ?? 'active',
            'first_name' => $user->personalData?->first_name,
            'profile_completion_required' => $user->requiresProfileCompletion(),
            'permissions' => $permissions,
            'branch_id' => $user->branch_id,
            'branch_name' => $user->branch?->name,
            'profile_photo_url' => $user->personalData?->profile_picture
                ? asset('storage/'.$user->personalData->profile_picture)
                : null,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfficerPermissionOverride;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\SecurityOtpService;
use App\Services\TurnstileService;
use App\Support\MaintenanceState;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->where(function($q) use ($emailNorm, $loginId) {
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

        $isOfficer = $user->hasAnyRole(['admin', 'manager', 'accountant', 'loan', 'secretary']);
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

        Auth::guard('web')->login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        $fingerprint = $this->securityOtpService->buildDeviceFingerprint($request);
        $redirect = $request->session()->pull('url.intended', route('dashboard', absolute: false));

        if ($this->securityOtpService->shouldEnforceOtpForUser($user)
            && ! $this->securityOtpService->isTrustedDevice($user, $fingerprint)) {
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

        $this->securityOtpService->trustDevice($user, $fingerprint, $request->userAgent(), $request->ip());
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

    public function logout(Request $request): JsonResponse
    {
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

        // Admins are superusers at the authorization layer (see AppServiceProvider),
        // so expose the same effective access to the React permission gates.
        if ($user->hasRole('admin')) {
            $permissions = array_values(array_unique(array_merge(
                $permissions,
                PermissionCatalog::allPermissions(),
            )));
        } elseif ($user->is_officer) {
            $overrides = OfficerPermissionOverride::query()
                ->where('user_id', $user->id)
                ->pluck('effect', 'permission');

            if ($overrides->isNotEmpty()) {
                $allowed = $overrides->filter(fn ($effect) => $effect === OfficerPermissionOverride::EFFECT_ALLOW)->keys()->all();
                $disallowed = $overrides->filter(fn ($effect) => $effect === OfficerPermissionOverride::EFFECT_DISALLOW)->keys()->all();

                $permissions = array_values(array_diff(array_unique(array_merge($permissions, $allowed)), $disallowed));
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => (bool) $user->hasVerifiedEmail(),
            'primary_role' => $user->primary_role,
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

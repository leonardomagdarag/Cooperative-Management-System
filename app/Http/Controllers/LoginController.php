<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\LoginHandoffService;
use App\Services\NotificationService;
use App\Services\SecurityOtpService;
use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    protected AuditLogService $auditLogService;

    protected NotificationService $notificationService;

    protected SecurityOtpService $securityOtpService;

    public function __construct(
        NotificationService $notificationService,
        SecurityOtpService $securityOtpService,
        AuditLogService $auditLogService
    ) {
        $this->notificationService = $notificationService;
        $this->securityOtpService = $securityOtpService;
        $this->auditLogService = $auditLogService;
    }

    public function login(Request $request, TurnstileService $turnstileService)
    {
        if (! app()->runningUnitTests()) {
            $turnstileToken = $request->input('cf-turnstile-response');
            if (! $turnstileService->verify($turnstileToken, $request->ip())) {
                $this->auditLogService->log(
                    'auth.login_blocked',
                    'Login blocked due to failed captcha verification.',
                    'auth',
                    null,
                    null,
                    ['ip_address' => $request->ip()]
                );

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Captcha verification failed. Please try again.',
                    ], 403);
                }

                return back()->withErrors([
                    'email' => 'Captcha verification failed. Please try again.',
                ]);
            }
        }

        $email = User::normalizeEmail($request->input('email'));
        $password = (string) $request->input('password');
        $ipAddress = (string) ($request->ip() ?? 'unknown');
        $previousSessionId = $request->session()->getId();

        // Attempt login
        if ($this->attemptLoginWithNormalizedEmailFallback($email, $password)) {
            $request->session()->regenerate(); // prevents session fixation

            $user = Auth::user();

            // Enforce role-specific login availability
            $isOfficer = $user->is_officer;
            $loginAllowed = $isOfficer
                ? (bool) SystemSetting::get('service_login_officer_enabled', true)
                : (bool) SystemSetting::get('service_login_member_enabled', true);

            if (! $loginAllowed) {
                $this->auditLogService->log(
                    'auth.login_blocked',
                    'Login blocked because login is disabled for this role.',
                    'auth',
                    $user,
                    $user,
                    ['user_type' => $isOfficer ? 'officer' : 'member']
                );

                Auth::logout();

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Login is currently unavailable for your account. Please try again later.',
                    ], 403);
                }

                return back()->withErrors([
                    'email' => 'Login is currently unavailable for your account. Please try again later.',
                ]);
            }

            // Check for inactive/terminated accounts
            if (($user->account_status ?? 'active') !== 'active') {
                $this->auditLogService->log(
                    'auth.login_blocked',
                    'Login blocked due to inactive account status.',
                    'auth',
                    $user,
                    $user,
                    ['account_status' => $user->account_status]
                );

                Auth::logout();

                // Return JSON for AJAX or redirect for regular form submission
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your account is not active.',
                    ], 403);
                }

                return back()->withErrors([
                    'email' => 'Your account is not active.',
                ]);
            }

            if ($user->must_set_password) {
                Auth::logout();

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Please set your password using the invitation sent to your email.'], 403);
                }

                return back()->withErrors(['email' => 'Please set your password using the invitation sent to your email.']);
            }

            $primaryRole = $user->primary_role;
            $redirectUrl = route('dashboard', absolute: false);

            $handoff = app(LoginHandoffService::class)->requestApproval($user, $previousSessionId, $request);
            if ($handoff) {
                $this->auditLogService->log('auth.login_approval_requested', 'A sign-in is waiting for approval from the active device.', 'auth', $handoff, $user, [
                    'device' => $handoff->device_label,
                    'ip_address' => $handoff->ip_address,
                ]);
                $request->session()->put('security.pending_login_handoff', $handoff->id);

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => true, 'requires_approval' => true]);
                }

                return redirect('/login/approval');
            }

            $fingerprint = $this->securityOtpService->buildDeviceFingerprint($request);
            $isNewDevice = ! $this->securityOtpService->isTrustedDevice($user, $fingerprint);
            $otpBypassed = ! $this->securityOtpService->shouldEnforceNewDeviceOtp();
            if (! $otpBypassed && $isNewDevice) {
                $issued = $this->securityOtpService->issueChallenge(
                    $user,
                    $this->securityOtpService->newDevicePurpose(),
                    ['fingerprint' => $fingerprint]
                );

                $this->securityOtpService->sendOtpMail(
                    $user, $this->securityOtpService->newDevicePurpose(), $issued['otp_code']
                );

                $request->session()->put('security.pending_otp', true);
                $request->session()->put('security.pending_otp_purpose', $this->securityOtpService->newDevicePurpose());
                $request->session()->put('security.pending_otp_fingerprint', $fingerprint);
                $request->session()->put('security.intended_url', $redirectUrl);
                $this->securityOtpService->stashDebugCode($request->session(), $issued['otp_code']);

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'redirect' => route('otp.challenge.show'),
                        'message' => 'Security verification is required for this device.',
                    ]);
                }

                return redirect()->route('otp.challenge.show')->with(
                    'success',
                    'We sent a one-time verification code to your email.'
                );
            }

            $this->securityOtpService->trustDevice(
                $user,
                $fingerprint,
                $request->userAgent(),
                $ipAddress,
                $request->session()->getId()
            );
            $user->activateSession($request->session()->getId());
            $user->invalidateOtherSessions($request->session()->getId());

            if ($isNewDevice) {
                $this->notificationService->notifyNewDeviceLogin($user, $ipAddress, $otpBypassed ? 'bypassed' : 'trusted');
            }

            $this->handleSuccessfulLoginSignals($user, $ipAddress, $isNewDevice);

            $this->auditLogService->log(
                'auth.login_success',
                'User logged in successfully.',
                'auth',
                $user,
                $user,
                [
                    'role' => $primaryRole,
                    'ip_address' => $ipAddress,
                ]
            );

            // Return JSON for AJAX or redirect for regular form submission
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'redirect' => $redirectUrl,
                ]);
            }

            return redirect()->intended($redirectUrl);
        }

        $this->handleFailedLoginSignals($email, $ipAddress);

        // Authentication failed
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ]);
    }

    private function failedLoginAttemptsKey(User $user): string
    {
        return 'security:failed_login_attempts:user:'.$user->id;
    }

    private function lastLoginIpKey(User $user): string
    {
        return 'security:last_login_ip:user:'.$user->id;
    }

    private function handleFailedLoginSignals(string $email, string $ipAddress): void
    {
        if ($email === '') {
            return;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return;
        }

        $key = $this->failedLoginAttemptsKey($user);
        $attempts = (int) Cache::increment($key);
        Cache::put($key, $attempts, now()->addMinutes(30));

        if ($attempts === 3 || $attempts % 5 === 0) {
            $this->notificationService->notifyFailedLogins($user, $attempts, $ipAddress);
        }

        $this->auditLogService->log(
            'security.failed_login',
            'Failed login attempt recorded.',
            'security',
            $user,
            $user,
            [
                'attempts' => $attempts,
                'ip_address' => $ipAddress,
            ]
        );
    }

    private function handleSuccessfulLoginSignals(User $user, string $ipAddress, bool $isNewDevice): void
    {
        Cache::forget($this->failedLoginAttemptsKey($user));

        $lastIpKey = $this->lastLoginIpKey($user);
        $previousIp = Cache::get($lastIpKey);

        if (! $isNewDevice && $previousIp && $previousIp !== $ipAddress) {
            $this->notificationService->notifyNewLogin($user, $ipAddress);
        }

        Cache::put($lastIpKey, $ipAddress, now()->addDays(90));
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $this->auditLogService->log(
                'auth.logout',
                'User logged out.',
                'auth',
                $user,
                $user
            );
            $user->clearCurrentSession($request->session()->getId());
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function attemptLoginWithNormalizedEmailFallback(string $email, string $password): bool
    {
        if (Auth::attempt([
            'email' => $email,
            'password' => $password,
        ])) {
            return true;
        }

        if ($email === '') {
            return false;
        }

        $matches = DB::table('users')
            ->select('id')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->pluck('id');

        $user = $matches
            ->map(fn ($id) => Auth::getProvider()->retrieveById($id))
            ->first(fn ($candidate) => $candidate instanceof User && Hash::check($password, $candidate->password));

        if (! $user instanceof User) {
            return false;
        }

        if ($user->email !== $email) {
            $user->forceFill(['email' => $email])->save();
        }

        Auth::login($user);

        return true;
    }
}

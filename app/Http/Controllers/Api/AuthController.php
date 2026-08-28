<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\SecurityOtpService;
use App\Services\TurnstileService;
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
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', User::normalizeEmail($request->input('email')))->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
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

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'primary_role' => $user->primary_role,
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'profile_photo_url' => $user->personalData?->profile_picture
                ? asset('storage/'.$user->personalData->profile_picture)
                : null,
        ];
    }
}

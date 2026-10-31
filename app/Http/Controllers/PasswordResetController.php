<?php

namespace App\Http\Controllers;

use App\Models\TrustedDevice;
use App\Models\LoginHandoff;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\TurnstileService;
use App\Support\PersonPasswordPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function sendResetLink(Request $request, TurnstileService $turnstileService): JsonResponse
    {
        $email = User::normalizeEmail($request->input('email'));
        $request->merge(['email' => $email]);
        $rules = ['email' => 'required|email'];

        if (! app()->runningUnitTests()) {
            $rules['cf-turnstile-response'] = ['required', function ($attribute, $value, $fail) use ($turnstileService, $request) {
                if (! $turnstileService->verify((string) $value, $request->ip())) {
                    $fail('Captcha verification failed. Please try again.');
                }
            }];
        }

        $request->validate($rules);
        $user = User::query()->where('email', $email)->first();

        if ($user && ($user->account_status ?? 'active') === 'active') {
            $status = Password::sendResetLink(['email' => $email]);
            $this->auditLogService->log(
                'security.password_reset_requested',
                'Password reset link requested.',
                'security', $user, $user,
                ['email' => $email, 'status' => $status]
            );
        } else {
            $this->auditLogService->log(
                'security.password_reset_requested',
                'Password reset requested for unknown or inactive account.',
                'security', $user, $user,
                ['email' => $email, 'account_found' => (bool) $user]
            );
        }

        // Keep the response identical for unknown, inactive, throttled, and valid accounts.
        return response()->json([
            'success' => true,
            'message' => 'If an active account exists with that email address, a password reset link has been sent.',
        ]);
    }

    public function showBrokerResetForm(string $token)
    {
        return redirect('/reset-password?'.http_build_query([
            'token' => $token,
            'email' => request()->query('email'),
        ]));
    }

    public function reset(Request $request): JsonResponse
    {
        $email = User::normalizeEmail($request->input('email'));
        $request->merge(['email' => $email]);
        $user = User::query()->where('email', $email)->first();
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => array_merge(PersonPasswordPolicy::passwordRules(false, PersonPasswordPolicy::normalizedNameTokens([
                $user?->first_name, $user?->mid_name, $user?->last_name,
            ])), ['confirmed']),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
                $user->invalidateOtherSessions();
                $user->clearCurrentSession();
                LoginHandoff::query()->where('user_id', $user->id)->whereIn('status', ['pending', 'approved'])
                    ->update(['status' => 'expired', 'decided_at' => now()]);
                TrustedDevice::query()->where('user_id', $user->id)->update(['is_active' => false]);
                app(NotificationService::class)->notifyPasswordChanged($user);
            }
        );

        $this->auditLogService->log(
            $status === Password::PASSWORD_RESET ? 'security.password_reset_completed' : 'security.password_reset_failed',
            $status === Password::PASSWORD_RESET ? 'Password reset completed.' : 'Password reset failed.',
            'security', $user, $user,
            ['method' => 'broker_link', 'email' => $email, 'status' => $status]
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['success' => true, 'message' => __($status)])
            : response()->json(['success' => false, 'message' => __($status)], 422);
    }
}

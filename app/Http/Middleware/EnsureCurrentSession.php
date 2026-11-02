<?php

namespace App\Http\Middleware;

use App\Models\LoginHandoff;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\SecurityOtpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureCurrentSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        if (! $user || $request->session()->get('security.pending_otp', false) || app()->runningUnitTests()) {
            return $next($request);
        }

        $sessionId = $request->session()->getId();

        $pendingForSession = LoginHandoff::query()->where('user_id', $user->id)
            ->where('pending_session_id', $sessionId)->whereIn('status', ['pending', 'approved', 'otp_pending', 'otp_bypassed', 'rejected', 'expired'])
            ->where('expires_at', '>', now())->exists();
        if ($pendingForSession) {
            if (in_array($request->path(), ['api/login-handoffs/status', 'api/login-handoffs/otp', 'api/login-handoffs/complete'], true)) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'This sign-in is waiting for approval on your active device.', 'reason' => 'login_approval_pending'], 409);
            }
        }

        $activeHandoff = LoginHandoff::query()->where('user_id', $user->id)
            ->where('current_session_id', $sessionId)->whereIn('status', ['pending', 'approved'])
            ->where('expires_at', '>', now())->exists();
        if ($activeHandoff && ! str_starts_with($request->path(), 'api/login-handoffs')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Approve or reject the pending sign-in to continue.', 'reason' => 'login_approval_required'], 409);
            }
        }

        // Adopt a valid pre-deployment session once. A conditional update makes
        // the first request win if two legacy sessions race during rollout.
        if (! $user->current_session_id) {
            $claimed = User::query()->whereKey($user->id)->whereNull('current_session_id')
                ->update(['current_session_id' => $sessionId]);
            $user->refresh();
            if ($claimed && $user->current_session_id === $sessionId) {
                $fingerprint = app(SecurityOtpService::class)->buildDeviceFingerprint($request);
                TrustedDevice::query()->where('user_id', $user->id)
                    ->where('fingerprint', $fingerprint)
                    ->where('is_active', true)
                    ->update(['session_id' => $sessionId]);
            }
        }

        if ($user->current_session_id === $sessionId) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your account was signed in on another device. Please sign in again.',
                'reason' => 'session_ended',
            ], 401);
        }

        return redirect()->route('login_page')->withErrors([
            'email' => 'Your account was signed in on another device. Please sign in again.',
        ]);
    }
}

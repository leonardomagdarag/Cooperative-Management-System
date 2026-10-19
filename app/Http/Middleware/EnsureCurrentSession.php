<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\TrustedDevice;
use App\Services\SecurityOtpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureCurrentSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        if (! $user || $request->session()->get('security.pending_otp', false)) {
            return $next($request);
        }

        $sessionId = $request->session()->getId();

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

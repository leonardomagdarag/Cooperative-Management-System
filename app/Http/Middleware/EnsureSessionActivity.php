<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSessionActivity
{
    private const LAST_ACTIVITY_KEY = 'auth.last_activity_at';

    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('web')->check()) {
            return $next($request);
        }

        $session = $request->session();
        $now = now()->timestamp;
        $lastActivity = $session->get(self::LAST_ACTIVITY_KEY);
        $lifetime = (int) config('session.lifetime') * 60;

        if ($lastActivity !== null && $now - (int) $lastActivity >= $lifetime) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired due to inactivity. Please sign in again.',
                    'reason' => 'session_expired',
                ], 401);
            }

            return redirect()->route('login_page');
        }

        if (! $request->isMethod('GET')) {
            $session->put(self::LAST_ACTIVITY_KEY, $now);
        } elseif ($lastActivity === null) {
            // Start tracking existing sessions on their first request after rollout.
            $session->put(self::LAST_ACTIVITY_KEY, $now);
        }

        return $next($request);
    }

}

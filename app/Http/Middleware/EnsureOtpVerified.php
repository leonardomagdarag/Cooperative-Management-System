<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOtpVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            return $next($request);
        }

        if (! $request->session()->get('security.pending_otp', false)) {
            return $next($request);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Security verification is required before continuing.',
                'redirect' => route('otp.challenge.show'),
            ], 423);
        }

        return redirect()->route('otp.challenge.show');
    }
}

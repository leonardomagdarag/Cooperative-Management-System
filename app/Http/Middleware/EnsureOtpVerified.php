<?php

namespace App\Http\Middleware;

use App\Models\OtpChallenge;
use App\Services\SecurityOtpService;
use Closure;
use Illuminate\Http\Request;

class EnsureOtpVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $pendingOtp = (bool) $request->session()->get('security.pending_otp', false);

        if (! $pendingOtp) {
            $hasActiveChallenge = OtpChallenge::query()
                ->active()
                ->where('user_id', $user->id)
                ->where('purpose', app(SecurityOtpService::class)->newDevicePurpose())
                ->exists();

            if ($hasActiveChallenge) {
                $pendingOtp = true;
                $request->session()->put('security.pending_otp', true);
            }
        }

        if (! $pendingOtp) {
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

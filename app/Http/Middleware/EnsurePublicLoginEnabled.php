<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Support\MaintenanceState;
use Closure;
use Illuminate\Http\Request;

class EnsurePublicLoginEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if ((bool) SystemSetting::get('service_login_enabled', true)) {
            return $next($request);
        }

        // Recorded here rather than in SettingsController so the notice also
        // appears when the switch was flipped straight in the database.
        MaintenanceState::syncLoginAvailability();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Login is temporarily unavailable. Please try again later.',
                'maintenance' => true,
                'reason' => MaintenanceState::REASON_LOGIN,
                'redirect' => '/maintenance',
            ], 503);
        }

        return response()->view('maintenance', [
            'maintenance' => MaintenanceState::describe(),
        ], 503);
    }
}

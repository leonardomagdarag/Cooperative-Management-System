<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;

class EnsurePublicMemberRegistrationEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (! (bool) SystemSetting::get('service_registration_enabled', true)
            || ! (bool) SystemSetting::get('service_registration_member_enabled', true)) {
            return redirect()->route('home_page');
        }

        return $next($request);
    }
}

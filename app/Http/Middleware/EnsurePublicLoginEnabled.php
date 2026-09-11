<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;

class EnsurePublicLoginEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (! (bool) SystemSetting::get('service_login_enabled', true)) {
            return redirect()->route('home_page');
        }

        return $next($request);
    }
}

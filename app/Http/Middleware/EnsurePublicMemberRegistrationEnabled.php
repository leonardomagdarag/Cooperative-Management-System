<?php

namespace App\Http\Middleware;

use App\Support\SiteContentCatalog;
use Closure;
use Illuminate\Http\Request;

class EnsurePublicMemberRegistrationEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (! SiteContentCatalog::memberRegistrationEnabled()) {
            return redirect()->route('home_page');
        }

        return $next($request);
    }
}

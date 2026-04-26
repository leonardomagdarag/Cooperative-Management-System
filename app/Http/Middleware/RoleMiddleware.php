<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        // Not logged in
        if (!$user) {
            return redirect('/login');
        }

        // Check if account is inactive or terminated
        if (($user->account_status ?? 'active') !== 'active') {
            Auth::logout();
            return redirect('/login')->with('error', 'Your account is not active.');
        }

        // Route role authorization must be based on Spatie roles only.
        $hasAllowedRole = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole($roles)
            : false;

        // If user doesn't have an allowed role
        if (! $hasAllowedRole) {
            return redirect('/')->with('error', 'unauthorized access');
        }

        return $next($request);
    }
}

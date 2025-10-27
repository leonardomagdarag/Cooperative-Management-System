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
        
        //check if account is inactive or terminated
        if (in_array($user->role, ['inactive', 'terminated'])) {
            Auth::logout();
            return redirect('/login')->with('error', 'Your account is not active.');
        }

        // If user doesn't have an allowed role
        if (!in_array($user->role, $roles)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}

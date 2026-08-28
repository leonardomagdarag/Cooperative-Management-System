<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        // Not logged in - prefer the auth middleware to handle this, but be safe
        if (! $user) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login_page'));
        }

        // Check if account is inactive or terminated
        if (($user->account_status ?? 'active') !== 'active') {
            Auth::logout();

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => 'Your account is not active.'], 403);
            }

            return redirect()->route('login_page')->with('error', 'Your account is not active.');
        }

        // Normalize and sanitize provided roles
        $roles = array_filter(array_map('trim', is_array($roles) ? $roles : [$roles]));

        // If no roles were provided or Spatie isn't available, deny access and log for debugging
        if (empty($roles) || ! method_exists($user, 'hasAnyRole')) {
            Log::warning('RoleMiddleware: missing roles or Spatie not configured', [
                'user_id' => $user->id ?? null,
                'roles' => $roles,
                'route' => $request->path(),
            ]);

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            abort(403, 'Unauthorized access.');
        }

        // Authorize by Spatie roles
        if (! $user->hasAnyRole($roles)) {
            Log::warning('RoleMiddleware: unauthorized role access', [
                'user_id' => $user->id ?? null,
                'required_roles' => $roles,
                'route' => $request->path(),
            ]);

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}

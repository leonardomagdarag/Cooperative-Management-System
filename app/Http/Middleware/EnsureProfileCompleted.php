<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileCompleted
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->requiresProfileCompletion()) {
            return response()->json([
                'message' => 'Profile completion required.',
                'action' => 'complete_profile',
            ], 403);
        }

        return $next($request);
    }
}

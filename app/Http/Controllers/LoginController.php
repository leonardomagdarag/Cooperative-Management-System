<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // Attempt login
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate(); // prevents session fixation

            $user = Auth::user();

            // Check for inactive/terminated accounts
            if (in_array($user->role, ['inactive', 'terminated'])) {
                Auth::logout();

                // Return JSON for AJAX or redirect for regular form submission
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your account is not active.',
                    ], 403);
                }

                return back()->withErrors([
                    'email' => 'Your account is not active.',
                ]);
            }

            // Role-based redirects
            $redirectUrl = match ($user->role) {
                'admin' => '/admin/dashboard',
                'manager' => '/manager/dashboard',
                'member' => '/member/dashboard',
                'secretary' => '/secretary/dashboard',
                'loan' => '/loan/dashboard',
                'treasurer' => 'treasurer/dashboard',
                //remove if in prod!
                'developer' => '/dev/home',
                default => 'member/dashboard'
            };

            // Return JSON for AJAX or redirect for regular form submission
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'redirect' => $redirectUrl,
                ]);
            }

            return redirect()->intended($redirectUrl);
        }

        // Authentication failed
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ]);
    }

    public function logout(Request $request)
    {
        if (Auth::logout()) {

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect('/');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
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
                return back()->withErrors([
                    'email' => 'Your account is not active.',
                ]);
            }

            // Role-based redirects
            return match ($user->role) {
                //development ONLY
                'developer' => redirect()->intended('/dev/dev_panel'),
                // for production
                'admin'   => redirect()->intended('/admin/dashboard'),
                'officer' => redirect()->intended('/officer/dashboard'),
                'member'  => redirect()->intended('/dashboard')
            };
        }

        // Authentication failed
        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

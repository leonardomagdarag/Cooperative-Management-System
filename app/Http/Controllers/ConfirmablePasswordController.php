<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfirmablePasswordController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function show(): Response
    {
        return response('Confirm Password', 200);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $request->session()->put('auth.password_confirmed_at', time());

        $user = $request->user();
        if ($user) {
            $this->auditLogService->log(
                'security.password_confirmed',
                'User confirmed password for a sensitive action.',
                'security',
                $user,
                $user
            );
        }

        return redirect()->intended('/profile');
    }
}

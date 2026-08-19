<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function notice(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        // Serve the SPA so the React VerifyEmailPage (resend + sign out) renders
        // instead of a dead plain-text page the user can get stuck on.
        return response()->view('react');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $request->fulfill();

            $this->auditLogService->log(
                'auth.email_verified',
                'User verified email address.',
                'auth',
                $user,
                $user
            );
        }

        return redirect()->to(route('dashboard', absolute: false).'?verified=1');
    }
}

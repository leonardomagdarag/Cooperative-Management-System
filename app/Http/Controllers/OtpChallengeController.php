<?php

namespace App\Http\Controllers;

use App\Services\SecurityOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OtpChallengeController extends Controller
{
    public function __construct(private readonly SecurityOtpService $securityOtpService) {}

    public function show(Request $request)
    {
        if (! Auth::check() || ! $request->session()->get('security.pending_otp', false)) {
            return redirect()->route('home_page');
        }

        return view('public.verify-otp');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'otp' => 'required|digits:6',
        ]);

        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login_page');
        }

        $purpose = (string) $request->session()->get('security.pending_otp_purpose', $this->securityOtpService->newDevicePurpose());
        $fingerprint = (string) $request->session()->get('security.pending_otp_fingerprint', '');

        $verified = $this->securityOtpService->verifyChallenge(
            $user,
            $purpose,
            (string) $request->input('otp'),
            ['fingerprint' => $fingerprint]
        );

        if (! $verified) {
            return back()->withErrors([
                'otp' => 'Invalid or expired verification code.',
            ]);
        }

        if ($fingerprint !== '') {
            $this->securityOtpService->trustDevice(
                $user,
                $fingerprint,
                $request->userAgent(),
                $request->ip()
            );
        }

        $request->session()->forget([
            'security.pending_otp',
            'security.pending_otp_purpose',
            'security.pending_otp_fingerprint',
        ]);

        $intended = (string) $request->session()->pull('security.intended_url', '/member/dashboard');

        return redirect()->to($intended)->with('success', 'Security verification successful.');
    }

    public function resend(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $request->session()->get('security.pending_otp', false)) {
            return redirect()->route('login_page');
        }

        $purpose = (string) $request->session()->get('security.pending_otp_purpose', $this->securityOtpService->newDevicePurpose());
        $fingerprint = (string) $request->session()->get('security.pending_otp_fingerprint', '');

        $issued = $this->securityOtpService->issueChallenge($user, $purpose, ['fingerprint' => $fingerprint]);
        $this->securityOtpService->sendOtpMail($user, $purpose, $issued['otp_code']);

        return back()->with('success', 'A new verification code was sent to your email.');
    }
}

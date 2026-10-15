<?php

namespace App\Http\Controllers;

use App\Models\OtpChallenge;
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
            'security.pending_otp_debug_code',
        ]);

        $intended = (string) $request->session()->pull('security.intended_url', route('dashboard', absolute: false));

        return redirect()->to($intended)->with('success', 'Security verification successful.');
    }

    public function resend(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $request->session()->get('security.pending_otp', false)) {
            return redirect()->route('login_page');
        }

        $purpose = (string) $request->session()->get('security.pending_otp_purpose', $this->securityOtpService->newDevicePurpose());

        // Enforce a minimum interval between sends so resend cannot be used to
        // farm unlimited fresh challenges for brute-forcing.
        $recentChallenge = OtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('created_at', '>', now()->subSeconds(30))
            ->latest('id')
            ->first();

        if ($recentChallenge) {
            $secondsLeft = max(1, (int) $recentChallenge->created_at->addSeconds(30)->diffInSeconds(now()));

            return back()->withErrors([
                'otp' => "Please wait {$secondsLeft} second(s) before requesting a new code.",
            ]);
        }

        $fingerprint = (string) $request->session()->get('security.pending_otp_fingerprint', '');

        $issued = $this->securityOtpService->issueChallenge($user, $purpose, ['fingerprint' => $fingerprint]);
        $this->securityOtpService->sendOtpMail($user, $purpose, $issued['otp_code']);
        $this->securityOtpService->stashDebugCode($request->session(), $issued['otp_code']);

        return back()->with('success', 'A new verification code was sent to your email.');
    }

    public function resendViaSms(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $request->session()->get('security.pending_otp', false)) {
            return redirect()->route('login_page');
        }

        if (! $this->securityOtpService->userHasVerifiedPhone($user)) {
            return back()->withErrors(['otp' => 'You do not have a verified mobile number registered.']);
        }

        $purpose = (string) $request->session()->get('security.pending_otp_purpose', $this->securityOtpService->newDevicePurpose());

        // Enforce a minimum interval between sends
        $recentChallenge = OtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('created_at', '>', now()->subSeconds(30))
            ->latest('id')
            ->first();

        if ($recentChallenge) {
            $secondsLeft = max(1, (int) $recentChallenge->created_at->addSeconds(30)->diffInSeconds(now()));

            return back()->withErrors([
                'otp' => "Please wait {$secondsLeft} second(s) before requesting a new code.",
            ]);
        }

        $fingerprint = (string) $request->session()->get('security.pending_otp_fingerprint', '');

        $issued = $this->securityOtpService->issueChallenge($user, $purpose, ['fingerprint' => $fingerprint]);
        $sentBySms = $this->securityOtpService->sendOtpSms($user, $purpose, $issued['otp_code']);
        $this->securityOtpService->stashDebugCode($request->session(), $issued['otp_code']);

        return back()->with('success', $sentBySms
            ? 'A new verification code was sent to your mobile number.'
            : 'SMS is unavailable. A new verification code was sent to your email.');
    }
}

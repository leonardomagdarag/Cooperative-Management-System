<?php

namespace App\Http\Controllers;

use App\Models\LoginHandoff;
use App\Models\OtpChallenge;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\SecurityOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OtpChallengeController extends Controller
{
    public function __construct(
        private readonly SecurityOtpService $securityOtpService,
        private readonly NotificationService $notificationService,
    ) {}

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
        $handoffId = $request->session()->get('security.pending_login_handoff');
        $handoff = null;
        $context = ['fingerprint' => $fingerprint];

        if ($purpose === $this->securityOtpService->newDevicePurpose() && $handoffId) {
            $handoff = LoginHandoff::query()
                ->whereKey($handoffId)
                ->where('user_id', $user->id)
                ->where('pending_session_id', $request->session()->getId())
                ->whereIn('status', ['approved', 'otp_pending'])
                ->where('expires_at', '>', now())
                ->first();

            if (! $handoff) {
                return back()->withErrors(['otp' => 'This sign-in request expired or was already handled. Please sign in again.']);
            }

            $context['login_handoff_id'] = $handoff->id;
        }

        $verified = $this->securityOtpService->verifyChallenge(
            $user,
            $purpose,
            (string) $request->input('otp'),
            $context
        );

        if (! $verified) {
            return back()->withErrors([
                'otp' => 'Invalid or expired verification code.',
            ]);
        }

        if ($handoff) {
            $completed = LoginHandoff::query()
                ->whereKey($handoff->id)
                ->where('user_id', $user->id)
                ->where('pending_session_id', $request->session()->getId())
                ->whereIn('status', ['approved', 'otp_pending'])
                ->where('expires_at', '>', now())
                ->update(['status' => 'completed', 'decided_at' => now()]);

            if (! $completed) {
                return back()->withErrors(['otp' => 'This sign-in request expired or was already handled. Please sign in again.']);
            }
        }

        if ($fingerprint !== '') {
            $this->securityOtpService->trustDevice(
                $user,
                $fingerprint,
                $request->userAgent(),
                $request->ip(),
                $request->session()->getId()
            );

            if ($purpose === $this->securityOtpService->newDevicePurpose()) {
                $this->notificationService->notifyNewDeviceLogin($user, $request->ip(), 'verified');
            }
        }

        if ($purpose === $this->securityOtpService->newDevicePurpose()) {
            $user->activateSession($request->session()->getId());
            $user->invalidateOtherSessions($request->session()->getId());
            if ($handoff) {
                app(AuditLogService::class)->log('auth.login_success', 'User completed a device sign-in with security verification.', 'auth', $handoff, $user, [
                    'role' => $user->primary_role,
                    'ip_address' => $request->ip(),
                ]);
            }
        }

        $request->session()->forget([
            'security.pending_otp',
            'security.pending_otp_purpose',
            'security.pending_otp_fingerprint',
            'security.pending_otp_debug_code',
            'security.pending_login_handoff',
            'security.login_handoff_otp_started',
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

        $issued = $this->securityOtpService->issueChallenge($user, $purpose, $this->challengeContext($request, $fingerprint));
        $this->securityOtpService->sendOtpMail($user, $purpose, $issued['otp_code']);
        $this->extendHandoffExpiry($request, $user);
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

        $issued = $this->securityOtpService->issueChallenge($user, $purpose, $this->challengeContext($request, $fingerprint));
        $sentBySms = $this->securityOtpService->sendOtpSms($user, $purpose, $issued['otp_code']);
        $this->extendHandoffExpiry($request, $user);
        $this->securityOtpService->stashDebugCode($request->session(), $issued['otp_code']);

        return back()->with('success', $sentBySms
            ? 'A new verification code was sent to your mobile number.'
            : 'SMS is unavailable. A new verification code was sent to your email.');
    }

    private function challengeContext(Request $request, string $fingerprint): array
    {
        $context = ['fingerprint' => $fingerprint];
        $handoffId = $request->session()->get('security.pending_login_handoff');

        if ($handoffId) {
            $context['login_handoff_id'] = (int) $handoffId;
        }

        return $context;
    }

    private function extendHandoffExpiry(Request $request, User $user): void
    {
        $handoffId = $request->session()->get('security.pending_login_handoff');
        if (! $handoffId) {
            return;
        }

        $otpExpiryMinutes = max(1, (int) SystemSetting::get('security_otp_expiry_minutes', 10));
        LoginHandoff::query()
            ->whereKey($handoffId)
            ->where('user_id', $user->id)
            ->where('pending_session_id', $request->session()->getId())
            ->whereIn('status', ['approved', 'otp_pending'])
            ->update(['expires_at' => now()->addMinutes($otpExpiryMinutes)]);
    }
}

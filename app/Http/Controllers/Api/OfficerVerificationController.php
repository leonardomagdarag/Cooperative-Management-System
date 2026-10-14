<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityOtpService;
use App\Services\TextBeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfficerVerificationController extends Controller
{
    public function __construct(
        private readonly SecurityOtpService $securityOtpService,
        private readonly TextBeeService $textBeeService
    ) {}

    public function sendEmailOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $user = Auth::user();
        if (! $user || (! $user->can('officers.create') && ! $user->can('members.create'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $email = $request->input('email');
        if (User::where('email', $email)->exists()) {
            return response()->json(['message' => 'Email is already in use.'], 422);
        }

        $request->session()->put('pending_officer_email_verification', $email);

        $purpose = 'officer_email_verify';
        $issued = $this->securityOtpService->issueChallenge($user, $purpose);

        $this->securityOtpService->sendOtpMail($user, $purpose, $issued['otp_code'], $email);
        $this->securityOtpService->stashDebugCode($request->session(), $issued['otp_code']);

        return response()->json([
            'message' => 'Verification code sent to email.',
            'expires_at' => $issued['challenge']->expires_at->toISOString(),
        ]);
    }

    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $request->validate([
            'otp_code' => 'required|string|digits:6',
        ]);

        $user = Auth::user();
        $pendingEmail = $request->session()->get('pending_officer_email_verification');

        if (! $pendingEmail) {
            return response()->json(['message' => 'No email verification in progress.'], 400);
        }

        $purpose = 'officer_email_verify';
        $verified = $this->securityOtpService->verifyChallenge($user, $purpose, $request->input('otp_code'));

        if (! $verified) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        // Tag this email as verified in the session for the final form submission
        $request->session()->put('verified_officer_email', $pendingEmail);
        $request->session()->forget('pending_officer_email_verification');

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function sendPhoneOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone_no' => 'required|string|min:10|max:15',
        ]);

        $user = Auth::user();
        if (! $user || (! $user->can('officers.create') && ! $user->can('members.create'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $this->textBeeService->isEnabled()) {
            return response()->json(['message' => 'SMS service is not enabled.'], 503);
        }

        $phoneNo = $request->input('phone_no');
        $request->session()->put('pending_officer_phone_verification', $phoneNo);

        $purpose = 'officer_phone_verify';
        $issued = $this->securityOtpService->issueChallenge($user, $purpose);

        $this->securityOtpService->sendOtpSmsToPhone($user, $phoneNo, $purpose, $issued['otp_code']);
        $this->securityOtpService->stashDebugCode($request->session(), $issued['otp_code']);

        return response()->json([
            'message' => 'Verification code sent to phone.',
            'expires_at' => $issued['challenge']->expires_at->toISOString(),
        ]);
    }

    public function verifyPhoneOtp(Request $request): JsonResponse
    {
        $request->validate([
            'otp_code' => 'required|string|digits:6',
        ]);

        $user = Auth::user();
        $pendingPhone = $request->session()->get('pending_officer_phone_verification');

        if (! $pendingPhone) {
            return response()->json(['message' => 'No phone verification in progress.'], 400);
        }

        $purpose = 'officer_phone_verify';
        $verified = $this->securityOtpService->verifyChallenge($user, $purpose, $request->input('otp_code'));

        if (! $verified) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        // Tag this phone as verified in the session for the final form submission
        $request->session()->put('verified_officer_phone', $pendingPhone);
        $request->session()->forget('pending_officer_phone_verification');

        return response()->json(['message' => 'Phone verified successfully.']);
    }
}

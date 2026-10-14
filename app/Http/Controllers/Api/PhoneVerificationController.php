<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\AuditLogService;
use App\Services\SecurityOtpService;
use App\Services\TextBeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhoneVerificationController extends Controller
{
    public function __construct(
        private readonly SecurityOtpService $securityOtpService,
        private readonly TextBeeService $textBeeService
    ) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone_no' => 'required|string|min:10|max:15',
        ]);

        if (! $this->textBeeService->isEnabled()) {
            return response()->json(['message' => 'SMS service is not enabled.'], 503);
        }

        $user = Auth::user();
        $phoneNo = $request->input('phone_no');

        // Optional: Ensure number isn't already used by another active user
        $exists = Contact::where('phone_no', $phoneNo)
            ->where('id', '!=', $user->contact?->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This phone number is already registered to another account.'], 422);
        }

        // Temporarily store the pending phone in session so we know what we're verifying
        $request->session()->put('pending_phone_verification', $phoneNo);

        $purpose = 'phone_verification';
        $issued = $this->securityOtpService->issueChallenge($user, $purpose);

        $this->securityOtpService->sendOtpSmsToPhone($user, $phoneNo, $purpose, $issued['otp_code']);
        $this->securityOtpService->stashDebugCode($request->session(), $issued['otp_code']);

        return response()->json([
            'message' => 'Verification code sent to your phone.',
            'expires_at' => $issued['challenge']->expires_at->toISOString(),
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'otp_code' => 'required|string|digits:6',
        ]);

        $user = Auth::user();
        $pendingPhone = $request->session()->get('pending_phone_verification');

        if (! $pendingPhone) {
            return response()->json(['message' => 'No phone verification in progress.'], 400);
        }

        $purpose = 'phone_verification';
        $verified = $this->securityOtpService->verifyChallenge($user, $purpose, $request->input('otp_code'));

        if (! $verified) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        // Verification successful, save the phone number
        $contact = $user->contact;
        if ($contact) {
            $contact->phone_no = $pendingPhone;
            $contact->phone_verified_at = now();
            $contact->save();
        }

        $request->session()->forget('pending_phone_verification');

        app(AuditLogService::class)->log(
            'member.profile.phone-verified',
            'Member verified their phone number.',
            'profile',
            $user,
            $user,
            ['phone' => $pendingPhone]
        );

        return response()->json(['message' => 'Phone number verified successfully.']);
    }
}

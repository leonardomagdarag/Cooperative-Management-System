<?php

namespace App\Services;

use App\Jobs\SendOtpEmailJob;
use App\Mail\OtpCodeMail;
use App\Models\Notification;
use App\Models\OtpChallenge;
use App\Models\SystemSetting;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SecurityOtpService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function shouldEnforceOtpForUser(User $user): bool
    {
        // Some legacy users still carry their role on the users table rather
        // than in the Spatie pivot. Keep the security decision consistent for
        // both representations.
        return $user->hasRole('member') || $user->primary_role === 'member';
    }

    public function newDevicePurpose(): string
    {
        return 'new_device_login';
    }

    public function withdrawalApprovalPurpose(): string
    {
        return 'withdrawal_approval';
    }

    public function passwordChangePurpose(): string
    {
        return 'password_change';
    }

    public function emailChangePurpose(): string
    {
        return 'email_change';
    }

    public function memberPasswordChangePurpose(): string
    {
        return 'member_password_change';
    }

    public function buildDeviceFingerprint(Request $request): string
    {
        $signature = implode('|', [
            (string) ($request->userAgent() ?? 'unknown-agent'),
            (string) $request->header('accept-language', 'unknown-lang'),
            (string) $request->header('sec-ch-ua-platform', 'unknown-platform'),
        ]);

        return hash('sha256', $signature);
    }

    public function isTrustedDevice(User $user, string $fingerprint): bool
    {
        return TrustedDevice::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->exists();
    }

    public function trustDevice(User $user, string $fingerprint, ?string $userAgent, ?string $ipAddress): void
    {
        $days = (int) SystemSetting::get('security_trusted_device_days', 90);
        $trustedUntil = now()->addDays(max(1, $days));

        TrustedDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'fingerprint' => $fingerprint,
            ],
            [
                'device_label' => $this->resolveDeviceLabel($userAgent),
                'last_ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'trusted_until' => $trustedUntil,
                'last_used_at' => now(),
                'is_active' => true,
            ]
        );

        $this->auditLogService->log(
            'security.device_trusted',
            'Device marked as trusted for user.',
            'security',
            $user,
            $user,
            [
                'fingerprint_prefix' => substr($fingerprint, 0, 12),
                'trusted_until' => $trustedUntil,
                'ip_address' => $ipAddress,
            ]
        );
    }

    public function issueChallenge(User $user, string $purpose, array $context = []): array
    {
        $ttlMinutes = (int) SystemSetting::get('security_otp_expiry_minutes', 10);
        $maxAttempts = (int) SystemSetting::get('security_otp_max_attempts', 5);

        $otpCode = (string) random_int(100000, 999999);

        $challenge = OtpChallenge::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($otpCode),
            'context_hash' => $this->buildContextHash($context),
            'context' => empty($context) ? null : $context,
            'attempts' => 0,
            'max_attempts' => max(1, $maxAttempts),
            'expires_at' => now()->addMinutes(max(1, $ttlMinutes)),
        ]);

        $this->notifyOtpIssued($user, $purpose);

        $this->auditLogService->log(
            'security.otp_issued',
            'OTP challenge issued.',
            'security',
            $challenge,
            $user,
            [
                'purpose' => $purpose,
                'expires_at' => $challenge->expires_at,
            ]
        );

        return [
            'challenge' => $challenge,
            'otp_code' => $otpCode,
        ];
    }

    public function verifyChallenge(User $user, string $purpose, string $otpCode, array $context = []): bool
    {
        $challenge = OtpChallenge::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('context_hash', $this->buildContextHash($context))
            ->latest('id')
            ->first();

        if (! $challenge) {
            $this->auditLogService->log(
                'security.otp_verify_failed',
                'OTP verification failed because challenge was not found or inactive.',
                'security',
                $user,
                $user,
                ['purpose' => $purpose]
            );

            return false;
        }

        if (! Hash::check($otpCode, $challenge->code_hash)) {
            $challenge->increment('attempts');

            $this->auditLogService->log(
                'security.otp_verify_failed',
                'OTP verification failed due to invalid code.',
                'security',
                $challenge,
                $user,
                [
                    'purpose' => $purpose,
                    'attempts' => $challenge->attempts,
                ]
            );

            return false;
        }

        $challenge->forceFill([
            'consumed_at' => now(),
        ])->save();

        $this->auditLogService->log(
            'security.otp_verified',
            'OTP challenge verified successfully.',
            'security',
            $challenge,
            $user,
            ['purpose' => $purpose]
        );

        return true;
    }

    public function hasVerifiedChallenge(User $user, string $purpose, array $context = [], int $minutes = 15): bool
    {
        return OtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('context_hash', $this->buildContextHash($context))
            ->whereNotNull('consumed_at')
            ->where('consumed_at', '>=', now()->subMinutes(max(1, $minutes)))
            ->exists();
    }

    public function sendOtpMail(User $user, string $purpose, string $otpCode): void
    {
        if (! $user->email || config('mail.default') === 'log') {
            return;
        }

        try {
            // OTP is time-sensitive — try async first, fall back to sync
            SendOtpEmailJob::dispatch($user->id, $purpose, $otpCode);
        } catch (Throwable $e) {
            // If job dispatch fails (e.g., queue connection down), send synchronously
            try {
                $mailable = new OtpCodeMail($user, $purpose, $otpCode);
                Mail::to($user->email)->send($mailable);
            } catch (Throwable $e2) {
                Log::error("OTP email failed for user {$user->id}: {$e2->getMessage()}");
            }
        }
    }

    private function buildContextHash(array $context): ?string
    {
        if (empty($context)) {
            return null;
        }

        ksort($context);

        return hash('sha256', json_encode($context));
    }

    private function resolveDeviceLabel(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Unknown device';
        }

        if (str_contains($userAgent, 'Windows')) {
            return 'Windows browser';
        }

        if (str_contains($userAgent, 'Macintosh')) {
            return 'Mac browser';
        }

        if (str_contains($userAgent, 'Android')) {
            return 'Android device';
        }

        if (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            return 'iOS device';
        }

        return 'Web browser';
    }

    private function notifyOtpIssued(User $user, string $purpose): void
    {
        $title = 'Security verification code sent';

        $message = match ($purpose) {
            'new_device_login' => 'A one-time verification code was sent to your email to verify a new device login.',
            'withdrawal_approval' => 'A one-time verification code was sent to your email to approve a high-value withdrawal.',
            'password_change' => 'A one-time verification code was sent to your email to confirm changing your password.',
            'email_change' => 'A one-time verification code was sent to your current email to confirm changing it.',
            'member_password_change' => 'A one-time verification code was sent to your email to confirm changing a member password.',
            default => 'A one-time verification code was sent to your email.',
        };

        Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'otp',
            'title' => $title,
            'message' => $message,
            'priority' => 'high',
            'action_url' => '/notifications',
            'expires_at' => now()->addMinutes((int) SystemSetting::get('security_otp_expiry_minutes', 10)),
        ]);
    }
}

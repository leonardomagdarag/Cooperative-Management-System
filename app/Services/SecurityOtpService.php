<?php

namespace App\Services;

use App\Mail\OtpCodeMail;
use App\Models\Notification;
use App\Models\OtpChallenge;
use App\Models\SystemSetting;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SecurityOtpService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function shouldEnforceNewDeviceOtp(): bool
    {
        return ! config('auth.login_otp_bypass', false);
    }

    public function newDevicePurpose(): string
    {
        return 'new_device_login';
    }

    public function withdrawalApprovalPurpose(): string
    {
        return 'withdrawal_approval';
    }

    public function ledgerReversalPurpose(): string
    {
        return 'ledger_reversal';
    }

    public function passwordChangePurpose(): string
    {
        return 'password_change';
    }

    public function trustedDeviceRevocationPurpose(): string
    {
        return 'trusted_device_revocation';
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

    public function trustDevice(User $user, string $fingerprint, ?string $userAgent, ?string $ipAddress, ?string $sessionId = null): void
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
                'session_id' => $sessionId,
            ]
        );

        OtpChallenge::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('purpose', $this->newDevicePurpose())
            ->update(['consumed_at' => now()]);

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

    public function sendOtpMail(User $user, string $purpose, string $otpCode, ?string $recipient = null): void
    {
        $recipient ??= $user->email;
        if (! $recipient) {
            return;
        }

        // Surface raw OTP in application logs only in local environments or when
        // explicitly using the log mailer driver to prevent leaking secrets in production.
        if (app()->environment('local') || config('mail.default') === 'log') {
            Log::info("OTP email ({$purpose}) for {$user->email}: {$otpCode}");
        }

        if (config('mail.default') === 'log') {
            return;
        }

        if (app()->environment('testing') && config('mail.default') === 'smtp') {
            Log::info("OTP email ({$purpose}) for {$user->email}: {$otpCode}");

            return;
        }

        // OTP is sent inline rather than queued. The challenge lives for
        // `security_otp_expiry_minutes` (10 by default), and on shared hosting
        // the queue only drains when cron fires. Waiting on that tick spent
        // most of the window in the jobs table, and a `backoff` retry could
        // not land before the code expired anyway. One SMTP round trip is
        // cheap next to losing the code to a worker gap.
        try {
            Mail::to($recipient)->send(new OtpCodeMail($user, $purpose, $otpCode));
        } catch (Throwable $e) {
            Log::error("OTP email failed for user {$user->id} ({$purpose}): {$e->getMessage()}");

            try {
                $this->auditLogService->log(
                    'email.failed',
                    "OTP email failed for user {$user->id}: {$e->getMessage()}",
                    'email',
                    null,
                    null,
                    ['user_id' => $user->id, 'purpose' => $purpose]
                );
            } catch (Throwable $e3) {
                Log::error("Failed to audit OTP email failure for user {$user->id}: {$e3->getMessage()}");
            }

            abort(503, 'Failed to send OTP email. Please try again later.');
        }
    }

    public function sendOtpSms(User $user, string $purpose, string $otpCode): bool
    {
        $phone = $user->contact?->phone_no;
        if (! $phone || ! $user->contact?->phone_verified_at) {
            $this->logOtpForTesting('SMS', $user, $purpose, $otpCode);
            $this->sendOtpMail($user, $purpose, $otpCode);

            return false;
        }

        return $this->sendOtpSmsToPhone($user, $phone, $purpose, $otpCode, null, true);
    }

    public function sendOtpSmsToPhone(User $user, string $phone, string $purpose, string $otpCode, ?string $message = null, bool $fallbackToEmail = false): bool
    {
        $this->logOtpForTesting('SMS', $user, $purpose, $otpCode);
        $sms = app(TextBeeService::class);
        $message ??= $this->otpSmsMessage($purpose, $otpCode);

        try {
            if ($sms->isEnabled() && $sms->send($phone, $message)) {
                return true;
            }

            Log::warning("OTP SMS unavailable for user {$user->id} ({$purpose})".($fallbackToEmail ? '; falling back to email.' : '.'));
        } catch (Throwable $e) {
            Log::warning("OTP SMS delivery threw for user {$user->id} ({$purpose})".($fallbackToEmail ? '; falling back to email: ' : ': ').$e->getMessage());
        }

        if ($fallbackToEmail) {
            $this->sendOtpMail($user, $purpose, $otpCode);
        }

        return false;
    }

    private function logOtpForTesting(string $channel, User $user, string $purpose, string $otpCode): void
    {
        if (app()->environment(['local', 'testing'])) {
            Log::info("OTP {$channel} ({$purpose}) for user {$user->id} ({$user->email}): {$otpCode}");
        }
    }

    private function otpSmsMessage(string $purpose, string $otpCode): string
    {
        $label = match ($purpose) {
            'new_device_login' => 'new device login',
            'withdrawal_approval' => 'withdrawal approval',
            'password_change' => 'password change',
            'trusted_device_revocation' => 'trusted device revocation',
            'email_change' => 'email address change',
            'member_password_change' => 'password reset',
            'officer_phone_verify', 'phone_verification' => 'phone verification',
            default => 'security verification',
        };

        return "Settlers CC: Your {$label} OTP is {$otpCode}. Valid for 10 mins. Never share this.";
    }

    public function userCanReceiveSms(User $user): bool
    {
        return $user->contact?->phone_verified_at !== null
            && $user->contact?->phone_no !== null
            && app(TextBeeService::class)->isEnabled();
    }

    public function userHasVerifiedPhone(User $user): bool
    {
        return $user->contact?->phone_verified_at !== null
            && $user->contact?->phone_no !== null;
    }

    public function stashDebugCode(Session $session, string $otpCode): void
    {
        // Development-only: let the OTP page surface the code so local testing
        // is never blocked by email delivery. Never stored outside local env.
        if (in_array(config('app.env'), ['local', 'testing'], true)) {
            $session->put('security.pending_otp_debug_code', $otpCode);
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

        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown system',
        };
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') && ! str_contains($userAgent, 'Edg/') => 'Chrome',
            str_contains($userAgent, 'Safari/') && ! str_contains($userAgent, 'Chrome/') => 'Safari',
            default => 'Browser',
        };

        return "{$browser} on {$os}";
    }

    public function deviceLabel(?string $userAgent): string
    {
        return $this->resolveDeviceLabel($userAgent);
    }

    private function notifyOtpIssued(User $user, string $purpose): void
    {
        $title = 'Security verification code sent';

        $message = match ($purpose) {
            'new_device_login' => 'A one-time verification code was sent to your email to verify a new device login.',
            'withdrawal_approval' => 'A one-time verification code was sent to your email to approve a high-value withdrawal.',
            'password_change' => 'A one-time verification code was sent to your email to confirm changing your password.',
            'trusted_device_revocation' => 'A one-time verification code was sent to your email to confirm revoking a trusted device.',
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

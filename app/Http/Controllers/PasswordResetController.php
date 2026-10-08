<?php

namespace App\Http\Controllers;

use App\Jobs\SendPasswordResetEmailJob;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\TurnstileService;
use App\Support\PersonPasswordPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class PasswordResetController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * Show the forgot password form
     */
    public function showForgotPasswordForm()
    {
        return view('public.forgot-password');
    }

    /**
     * Handle forgot password request and send reset email
     */
    public function sendResetLink(Request $request, TurnstileService $turnstileService)
    {
        $email = User::normalizeEmail($request->input('email'));
        $request->merge(['email' => $email]);

        $rules = ['email' => 'required|email'];

        if (! app()->runningUnitTests()) {
            $rules['cf-turnstile-response'] = [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) use ($turnstileService, $request) {
                    if (! $turnstileService->verify((string) $value, $request->ip())) {
                        $fail('Captcha verification failed. Please try again.');
                    }
                },
            ];
        }

        $request->validate($rules);

        // Check if user is active
        $user = User::query()->where('email', $email)->first();

        if (! $user || ($user->account_status ?? 'active') !== 'active') {
            $this->auditLogService->log(
                'security.password_reset_requested',
                'Password reset requested for unknown or inactive account.',
                'security',
                $user,
                $user,
                [
                    'email' => $email,
                    'account_found' => (bool) $user,
                    'is_active' => (bool) ($user && ($user->account_status ?? 'active') === 'active'),
                ]
            );

            // The SPA submits this endpoint over XHR, so a redirect is followed as a
            // cross-origin request that fails and surfaces as "could not send the code"
            // even though the code went out. Answer JSON in that case. The wording stays
            // deliberately vague so the response cannot be used to probe for accounts.
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'If an account exists with that email address, a reset code has been sent.',
                ]);
            }

            return back()->with('success', 'If an account exists with that email address, a reset code has been sent.');
        }

        // Delete any existing tokens for this email
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Generate a random 6-digit token
        $token = random_int(100000, 999999);

        // Store token in database
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Send email with token
        try {
            SendPasswordResetEmailJob::dispatchSync($user->id, $token);

            $this->auditLogService->log(
                'security.password_reset_code_sent',
                'Password reset code sent to user email.',
                'security',
                $user,
                $user,
                [
                    'email' => $email,
                ]
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'A 6-digit reset code has been sent to your email address.',
                ]);
            }

            // Redirect to reset form with email
            return redirect()->route('password.reset.form', ['email' => $email])
                ->with('success', 'A 6-digit reset code has been sent to your email address.');
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Password reset email failed: '.$e->getMessage());
            report($e);

            $this->auditLogService->log(
                'security.password_reset_code_send_failed',
                'Failed to send password reset code.',
                'security',
                $user,
                $user,
                [
                    'email' => $email,
                ]
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send reset email. Please try again in a few minutes.',
                ], 500);
            }

            return back()->with('error', 'Failed to send reset email. Please try again in a few minutes.');
        }
    }

    /**
     * Show the reset password form
     */
    public function showResetForm(Request $request)
    {
        $email = $request->query('email');

        if (! $email) {
            return redirect()->route('password.request')
                ->with('error', 'Invalid reset link.');
        }

        return view('public.reset-password', ['email' => $email]);
    }

    public function showBrokerResetForm(string $token)
    {
        return response('Reset Password', 200);
    }

    /**
     * Verify token only (AJAX endpoint)
     */
    public function verifyToken(Request $request)
    {
        $email = User::normalizeEmail($request->input('email'));
        $request->merge(['email' => $email]);

        $request->validate([
            'email' => 'required|email',
            'token' => 'required|digits:6',
        ]);

        $user = User::query()->where('email', $email)->first();

        // Find the password reset record
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $resetRecord) {
            $this->auditLogService->log(
                'security.password_reset_verify_failed',
                'Password reset verification failed: reset record missing.',
                'security',
                $user,
                $user,
                ['email' => $email]
            );

            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired reset code.',
            ], 400);
        }

        // Check if token expired (30 minutes)
        if (now()->diffInMinutes($resetRecord->created_at) > 30) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            $this->auditLogService->log(
                'security.password_reset_verify_failed',
                'Password reset verification failed: reset code expired.',
                'security',
                $user,
                $user,
                ['email' => $email]
            );

            return response()->json([
                'valid' => false,
                'message' => 'Reset code has expired. Please request a new one.',
            ], 400);
        }

        // Verify token
        if (! Hash::check($request->token, $resetRecord->token)) {
            $this->auditLogService->log(
                'security.password_reset_verify_failed',
                'Password reset verification failed: invalid reset code.',
                'security',
                $user,
                $user,
                ['email' => $email]
            );

            return response()->json([
                'valid' => false,
                'message' => 'Invalid reset code. Please check and try again.',
            ], 400);
        }

        $this->auditLogService->log(
            'security.password_reset_code_verified',
            'Password reset code verified successfully.',
            'security',
            $user,
            $user,
            ['email' => $email]
        );

        return response()->json([
            'valid' => true,
            'message' => 'Code verified successfully.',
        ]);
    }

    /**
     * Handle password reset
     */
    public function reset(Request $request)
    {
        $email = User::normalizeEmail($request->input('email'));
        $token = (string) $request->input('token');

        $request->merge(['email' => $email]);

        if (! preg_match('/^\d{6}$/', $token)) {
            $subjectUser = User::query()->where('email', $email)->first();

            $request->validate([
                'token' => 'required|string',
                'email' => 'required|email',
                'password' => array_merge(PersonPasswordPolicy::passwordRules(false, PersonPasswordPolicy::normalizedNameTokens([
                    $subjectUser?->first_name,
                    $subjectUser?->mid_name,
                    $subjectUser?->last_name,
                ])), ['confirmed']),
            ]);

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password): void {
                    $user->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordReset($user));
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                $this->auditLogService->log(
                    'security.password_reset_completed',
                    'Password reset completed via broker token flow.',
                    'security',
                    $subjectUser,
                    $subjectUser,
                    [
                        'method' => 'broker_token',
                        'email' => $email,
                    ]
                );
            } else {
                $this->auditLogService->log(
                    'security.password_reset_failed',
                    'Password reset failed via broker token flow.',
                    'security',
                    $subjectUser,
                    $subjectUser,
                    [
                        'method' => 'broker_token',
                        'email' => $email,
                        'status' => $status,
                    ]
                );
            }

            return $status === Password::PASSWORD_RESET
                ? redirect()->route('login')->with('status', __($status))
                : back()->withErrors(['email' => [__($status)]]);
        }

        $resetUser = User::query()->where('email', $email)->first();

        $request->validate([
            'email' => 'required|email',
            'token' => 'required|digits:6',
            'password' => array_merge(PersonPasswordPolicy::passwordRules(false, PersonPasswordPolicy::normalizedNameTokens([
                $resetUser?->first_name,
                $resetUser?->mid_name,
                $resetUser?->last_name,
            ])), ['confirmed']),
        ], [
            'token.digits' => 'The reset code must be 6 digits.',
        ]);

        // Find the password reset record
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        $user = $resetUser;

        if (! $resetRecord) {
            $this->auditLogService->log(
                'security.password_reset_failed',
                'Password reset failed: reset record missing.',
                'security',
                $user,
                $user,
                [
                    'method' => 'otp_code',
                    'email' => $email,
                ]
            );

            return back()->with('error', 'Invalid or expired reset code.');
        }

        // Check if token expired (30 minutes)
        if (now()->diffInMinutes($resetRecord->created_at) > 30) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            $this->auditLogService->log(
                'security.password_reset_failed',
                'Password reset failed: reset code expired.',
                'security',
                $user,
                $user,
                [
                    'method' => 'otp_code',
                    'email' => $email,
                ]
            );

            return back()->with('error', 'Reset code has expired. Please request a new one.');
        }

        // Verify token
        if (! Hash::check($request->token, $resetRecord->token)) {
            $this->auditLogService->log(
                'security.password_reset_failed',
                'Password reset failed: invalid reset code.',
                'security',
                $user,
                $user,
                [
                    'method' => 'otp_code',
                    'email' => $email,
                ]
            );

            return back()->with('error', 'Invalid reset code.');
        }

        // Update user password
        if (! $user) {
            $this->auditLogService->log(
                'security.password_reset_failed',
                'Password reset failed: user account missing.',
                'security',
                null,
                null,
                [
                    'method' => 'otp_code',
                    'email' => $email,
                ]
            );

            return back()->with('error', 'Unable to complete password reset. Please try again.');
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Invalidate existing sessions and revoke trusted devices so a stolen
        // session/cookie cannot keep working after the password is reset.
        $user->invalidateOtherSessions();
        TrustedDevice::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        app(NotificationService::class)->notifyPasswordChanged($user);

        // Delete the reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Password changed notification is already sent via notifyPasswordChanged() above

        $this->auditLogService->log(
            'security.password_reset_completed',
            'Password reset completed via OTP code flow.',
            'security',
            $user,
            $user,
            [
                'method' => 'otp_code',
                'email' => $email,
            ]
        );

        return redirect()->route('login_page')
            ->with('success', 'Your password has been reset successfully. You can now login with your new password.');
    }

    /**
     * Send reset email using PHPMailer
     */
    private function sendResetEmail($user, $token)
    {
        // Validate email configuration first
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name', 'Cooperative System');
        $mailHost = config('mail.mailers.smtp.host');
        $mailUsername = config('mail.mailers.smtp.username');
        $mailPassword = config('mail.mailers.smtp.password');

        if (! $fromAddress || ! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('MAIL_FROM_ADDRESS is not configured properly in .env file. Please set a valid email address.');
        }

        if (! $mailHost || ! $mailUsername || ! $mailPassword) {
            throw new \Exception('Email configuration incomplete. Please check MAIL_HOST, MAIL_USERNAME, and MAIL_PASSWORD in .env file.');
        }

        $mail = new PHPMailer(true);

        try {
            // Debug mode (set to 0 for production)
            // $mail->SMTPDebug = 2; // Uncomment to see detailed SMTP debug output

            // Server settings
            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->SMTPAuth = true;
            $mail->Username = $mailUsername;
            $mail->Password = $mailPassword;
            $mail->SMTPSecure = config('mail.mailers.smtp.encryption', 'tls');
            $mail->Port = config('mail.mailers.smtp.port', 587);

            // Timeout settings
            $mail->Timeout = 30;

            // Recipients
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($user->email, $user->personalData->first_name.' '.$user->personalData->last_name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';

            $mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #0891b2 0%, #10b981 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
                        .token-box { background: white; border: 2px solid #0891b2; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                        .token { font-size: 32px; font-weight: bold; color: #0891b2; letter-spacing: 8px; }
                        .footer { background: #e5e7eb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 10px 10px; }
                        .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Password Reset Request</h1>
                        </div>
                        <div class='content'>
                            <p>Hello <strong>{$user->personalData->first_name}</strong>,</p>
                            <p>We received a request to reset your password for your Cooperative Management System account.</p>
                            
                            <div class='token-box'>
                                <p style='margin: 0; font-size: 14px; color: #6b7280;'>Your Reset Code:</p>
                                <div class='token'>{$token}</div>
                                <p style='margin: 10px 0 0 0; font-size: 12px; color: #6b7280;'>Valid for 30 minutes</p>
                            </div>
                            
                            <p>Enter this code on the password reset page to create a new password.</p>
                            
                            <div class='warning'>
                                <strong>⚠️ Security Notice:</strong> If you did not request a password reset, please ignore this email and your password will remain unchanged. For security, consider changing your password if you did not make this request.
                            </div>
                        </div>
                        <div class='footer'>
                            <p>&copy; ".date('Y').' Cooperative Management System. All rights reserved.</p>
                            <p>This is an automated message, please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ';

            $mail->AltBody = "Your password reset code is: {$token}. This code is valid for 30 minutes.";

            $mail->send();
        } catch (Exception $e) {
            throw new \Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }

    /**
     * Send password changed confirmation email
     */
    private function sendPasswordChangedEmail($user)
    {
        // Validate email configuration first
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name', 'Cooperative System');

        if (! $fromAddress || ! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('MAIL_FROM_ADDRESS is not configured properly in .env file.');
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = config('mail.mailers.smtp.host');
            $mail->SMTPAuth = true;
            $mail->Username = config('mail.mailers.smtp.username');
            $mail->Password = config('mail.mailers.smtp.password');
            $mail->SMTPSecure = config('mail.mailers.smtp.encryption', 'tls');
            $mail->Port = config('mail.mailers.smtp.port', 587);

            // Recipients
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($user->email, $user->personalData->first_name.' '.$user->personalData->last_name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Changed Successfully';

            $mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #10b981 0%, #0891b2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
                        .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; }
                        .footer { background: #e5e7eb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 10px 10px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>✓ Password Changed</h1>
                        </div>
                        <div class='content'>
                            <p>Hello <strong>{$user->personalData->first_name}</strong>,</p>
                            
                            <div class='success'>
                                <strong>Success!</strong> Your password has been changed successfully.
                            </div>
                            
                            <p>Your password was changed on <strong>".now()->format('F d, Y \a\t h:i A')."</strong>.</p>
                            
                            <p>If you did not make this change, please contact the administrator immediately.</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; ".date('Y').' Cooperative Management System. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ';

            $mail->send();
        } catch (Exception $e) {
            // Silently fail - password is already changed
        }
    }
}

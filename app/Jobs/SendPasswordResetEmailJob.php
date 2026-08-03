<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class SendPasswordResetEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public string $token,
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user || ! $user->email) {
            return;
        }

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name', 'Cooperative System');
        $mailHost = config('mail.mailers.smtp.host');
        $mailUsername = config('mail.mailers.smtp.username');
        $mailPassword = config('mail.mailers.smtp.password');

        if (! $fromAddress || ! $mailHost || ! $mailUsername || ! $mailPassword) {
            Log::error('Email configuration incomplete for password reset');

            return;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->SMTPAuth = true;
            $mail->Username = $mailUsername;
            $mail->Password = $mailPassword;
            $mail->SMTPSecure = config('mail.mailers.smtp.encryption', 'tls');
            $mail->Port = config('mail.mailers.smtp.port', 587);
            $mail->Timeout = 30;

            $name = trim(($user->personalData?->first_name ?? '').' '.($user->personalData?->last_name ?? '')) ?: $user->email;
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($user->email, $name);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body = $this->buildResetEmailBody($user);
            $mail->AltBody = "Your password reset code is: {$this->token}. This code is valid for 30 minutes.";

            $mail->send();
        } catch (Exception $e) {
            Log::error("Password reset email failed for user {$this->userId}: {$mail->ErrorInfo}");
            app(AuditLogService::class)->log(
                'email.failed',
                "Password reset email failed for user {$this->userId}: {$mail->ErrorInfo}",
                'email', null, null,
                ['user_id' => $this->userId]
            );
            throw $e;
        }
    }

    private function buildResetEmailBody(User $user): string
    {
        $name = trim(($user->personalData?->first_name ?? '').' '.($user->personalData?->last_name ?? '')) ?: $user->email;

        return "
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
                        <p>Hello <strong>{$name}</strong>,</p>
                        <p>We received a request to reset your password.</p>
                        <div class='token-box'>
                            <p style='margin: 0; font-size: 14px; color: #6b7280;'>Your Reset Code:</p>
                            <div class='token'>{$this->token}</div>
                            <p style='margin: 10px 0 0 0; font-size: 12px; color: #6b7280;'>Valid for 30 minutes</p>
                        </div>
                        <p>Enter this code on the password reset page to create a new password.</p>
                        <div class='warning'>
                            <strong>Security Notice:</strong> If you did not request a password reset, please ignore this email.
                        </div>
                    </div>
                    <div class='footer'>
                        <p>&copy; ".date('Y').' Settlers Credit Cooperative. All rights reserved.</p>
                        <p>This is an automated message, please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
    }
}

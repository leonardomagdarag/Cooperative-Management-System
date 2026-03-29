<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class PasswordResetController extends Controller
{
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
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'We could not find a user with that email address.',
        ]);

        // Check if user is active (role should not be 'terminated')
        $user = User::where('email', $request->email)->first();

        if ($user->role === 'terminated') {
            return back()->with('error', 'Your account has been terminated. Please contact the administrator.');
        }

        // Delete any existing tokens for this email
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Generate a random 6-digit token
        $token = random_int(100000, 999999);

        // Store token in database
        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Send email with token
        try {
            $this->sendResetEmail($user, $token);

            // Redirect to reset form with email
            return redirect()->route('password.reset.form', ['email' => $request->email])
                ->with('success', 'A 6-digit reset code has been sent to your email address.');
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Password reset email failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to send reset email: ' . $e->getMessage());
        }
    }

    /**
     * Show the reset password form
     */
    public function showResetForm(Request $request)
    {
        $email = $request->query('email');

        if (!$email) {
            return redirect()->route('password.request')
                ->with('error', 'Invalid reset link.');
        }

        return view('public.reset-password', ['email' => $email]);
    }

    /**
     * Verify token only (AJAX endpoint)
     */
    public function verifyToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|digits:6',
        ]);

        // Find the password reset record
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired reset code.'
            ], 400);
        }

        // Check if token expired (30 minutes)
        if (now()->diffInMinutes($resetRecord->created_at) > 30) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'valid' => false,
                'message' => 'Reset code has expired. Please request a new one.'
            ], 400);
        }

        // Verify token
        if (!Hash::check($request->token, $resetRecord->token)) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid reset code. Please check and try again.'
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Code verified successfully.'
        ]);
    }

    /**
     * Handle password reset
     */
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|digits:6',
            'password' => 'required|min:8|confirmed',
        ], [
            'token.digits' => 'The reset code must be 6 digits.',
        ]);

        // Find the password reset record
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            return back()->with('error', 'Invalid or expired reset code.');
        }

        // Check if token expired (30 minutes)
        if (now()->diffInMinutes($resetRecord->created_at) > 30) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->with('error', 'Reset code has expired. Please request a new one.');
        }

        // Verify token
        if (!Hash::check($request->token, $resetRecord->token)) {
            return back()->with('error', 'Invalid reset code.');
        }

        // Update user password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Send confirmation email
        try {
            $this->sendPasswordChangedEmail($user);
        } catch (\Exception $e) {
            // Continue even if email fails
        }

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

        if (!$fromAddress || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('MAIL_FROM_ADDRESS is not configured properly in .env file. Please set a valid email address.');
        }

        if (!$mailHost || !$mailUsername || !$mailPassword) {
            throw new \Exception('Email configuration incomplete. Please check MAIL_HOST, MAIL_USERNAME, and MAIL_PASSWORD in .env file.');
        }

        $mail = new PHPMailer(true);

        try {
            // Debug mode (set to 0 for production)
            // $mail->SMTPDebug = 2; // Uncomment to see detailed SMTP debug output

            // Server settings
            $mail->isSMTP();
            $mail->Host       = $mailHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $mailUsername;
            $mail->Password   = $mailPassword;
            $mail->SMTPSecure = config('mail.mailers.smtp.encryption', 'tls');
            $mail->Port       = config('mail.mailers.smtp.port', 587);

            // Timeout settings
            $mail->Timeout    = 30;

            // Recipients
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($user->email, $user->personalData->first_name . ' ' . $user->personalData->last_name);

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
                            <p>&copy; " . date('Y') . " Cooperative Management System. All rights reserved.</p>
                            <p>This is an automated message, please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

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

        if (!$fromAddress || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('MAIL_FROM_ADDRESS is not configured properly in .env file.');
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = config('mail.mailers.smtp.host');
            $mail->SMTPAuth   = true;
            $mail->Username   = config('mail.mailers.smtp.username');
            $mail->Password   = config('mail.mailers.smtp.password');
            $mail->SMTPSecure = config('mail.mailers.smtp.encryption', 'tls');
            $mail->Port       = config('mail.mailers.smtp.port', 587);

            // Recipients
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($user->email, $user->personalData->first_name . ' ' . $user->personalData->last_name);

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
                            
                            <p>Your password was changed on <strong>" . now()->format('F d, Y \a\t h:i A') . "</strong>.</p>
                            
                            <p>If you did not make this change, please contact the administrator immediately.</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " Cooperative Management System. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            $mail->send();
        } catch (Exception $e) {
            // Silently fail - password is already changed
        }
    }
}

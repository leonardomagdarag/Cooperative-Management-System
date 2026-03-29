<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'email:test {recipient?}';

    /**
     * The console command description.
     */
    protected $description = 'Test email configuration and send a test email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking Email Configuration...');
        $this->newLine();

        // Check .env configuration
        $this->info('📋 Current Email Settings:');
        $this->line('MAIL_HOST: ' . (env('MAIL_HOST') ?: '❌ Not set'));
        $this->line('MAIL_PORT: ' . (env('MAIL_PORT') ?: '❌ Not set'));
        $this->line('MAIL_USERNAME: ' . (env('MAIL_USERNAME') ?: '❌ Not set'));
        $this->line('MAIL_PASSWORD: ' . (env('MAIL_PASSWORD') ? '✅ Set (hidden)' : '❌ Not set'));
        $this->line('MAIL_ENCRYPTION: ' . (env('MAIL_ENCRYPTION') ?: '❌ Not set'));
        $this->line('MAIL_FROM_ADDRESS: ' . (env('MAIL_FROM_ADDRESS') ?: '❌ Not set'));
        $this->line('MAIL_FROM_NAME: ' . (env('MAIL_FROM_NAME') ?: '❌ Not set'));
        $this->newLine();

        // Check if all required settings are present
        $required = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'];
        $missing = [];
        foreach ($required as $key) {
            if (!env($key)) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            $this->error('❌ Missing required configuration: ' . implode(', ', $missing));
            $this->newLine();
            $this->info('Please update your .env file with proper mail settings.');
            $this->info('See EMAIL_SETUP_GUIDE.md for detailed instructions.');
            return 1;
        }

        // Ask for recipient
        $recipient = $this->argument('recipient');
        if (!$recipient) {
            $recipient = $this->ask('Enter recipient email address');
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('❌ Invalid email address');
            return 1;
        }

        // Try to send test email
        $this->info('📧 Sending test email to: ' . $recipient);
        $this->newLine();

        try {
            $mail = new PHPMailer(true);

            // Enable verbose debug output
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function ($str, $level) {
                $this->line('   ' . trim($str));
            };

            // Server settings
            $mail->isSMTP();
            $mail->Host       = env('MAIL_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('MAIL_USERNAME');
            $mail->Password   = env('MAIL_PASSWORD');
            $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls');
            $mail->Port       = env('MAIL_PORT', 587);
            $mail->Timeout    = 30;

            // Recipients
            $mail->setFrom(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            $mail->addAddress($recipient);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Test Email from Cooperative Management System';
            $mail->Body    = '<h1>Test Email</h1><p>If you received this email, your mail configuration is working correctly! ✅</p>';
            $mail->AltBody = 'Test Email - If you received this email, your mail configuration is working correctly!';

            $mail->send();

            $this->newLine();
            $this->info('✅ Test email sent successfully!');
            $this->info('Check the inbox of: ' . $recipient);
            return 0;
        } catch (Exception $e) {
            $this->newLine();
            $this->error('❌ Failed to send email!');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();

            $this->info('💡 Common Solutions:');
            if (str_contains($e->getMessage(), 'authenticate')) {
                $this->line('   • Check your MAIL_USERNAME and MAIL_PASSWORD');
                $this->line('   • For Gmail, make sure you\'re using an App Password, not your account password');
            } elseif (str_contains($e->getMessage(), 'connect')) {
                $this->line('   • Check your MAIL_HOST and MAIL_PORT');
                $this->line('   • Make sure your firewall allows SMTP connections');
                $this->line('   • Try different ports: 587 (TLS) or 465 (SSL)');
            } elseif (str_contains($e->getMessage(), 'timed out')) {
                $this->line('   • Your firewall might be blocking the connection');
                $this->line('   • Check if your hosting provider blocks SMTP');
            }

            return 1;
        }
    }
}

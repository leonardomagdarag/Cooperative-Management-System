<?php

namespace App\Console\Commands;

use App\Mail\LoanDueNotification;
use App\Mail\SecurityAlert;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mail:test {email}';

    /**
     * The console command description.
     */
    protected $description = 'Test email configuration by sending a test email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        $this->info('Testing email configuration...');
        $this->info("Sending test email to: {$email}");

        try {
            // Send a simple test email
            Mail::raw('✅ Success! Your email configuration is working correctly. This is a test email from the Settlers Credit Cooperative system.', function ($message) use ($email) {
                $message->to($email)
                    ->subject('Test Email - Cooperative Management System');
            });

            $this->info('✅ Test email sent successfully!');
            $this->info('Check your inbox (and spam folder) for the test email.');

            // Optionally test a formatted email
            if ($this->confirm('Would you like to test a formatted notification email?', true)) {
                $user = User::first();

                if (!$user) {
                    $this->warn('No users found in database. Skipping formatted email test.');
                    return Command::SUCCESS;
                }

                Mail::to($email)->send(new SecurityAlert($user, 'failed_login', [
                    'attempts' => 3,
                    'ip_address' => '192.168.1.100',
                ]));

                $this->info('✅ Formatted security alert email sent!');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to send email!');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Troubleshooting tips:');
            $this->line('1. Check your .env file for correct MAIL_* settings');
            $this->line('2. Run: php artisan config:clear');
            $this->line('3. Check storage/logs/laravel.log for details');
            $this->line('4. Verify SMTP credentials are correct');

            return Command::FAILURE;
        }
    }
}

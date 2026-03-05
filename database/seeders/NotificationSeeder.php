<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first user for testing
        $user = User::first();

        if (!$user) {
            $this->command->warn('No users found. Please create users first.');
            return;
        }

        $notifications = [
            [
                'user_id' => $user->id,
                'type' => 'announcement',
                'title' => 'New System Update',
                'message' => 'The system has been updated with new features. Check out the latest improvements.',
                'priority' => 'normal',
                'action_url' => '/announcements',
                'is_read' => false,
            ],
            [
                'user_id' => $user->id,
                'type' => 'loan_due',
                'title' => 'Loan Payment Due Soon',
                'message' => 'Your loan payment of ₱50,000.00 is due in 3 day(s).',
                'priority' => 'high',
                'action_url' => '/loans',
                'is_read' => false,
            ],
            [
                'user_id' => $user->id,
                'type' => 'loan_due',
                'title' => 'Urgent: Loan Payment Due Tomorrow',
                'message' => 'Your loan payment of ₱25,000.00 is due in 1 day(s). Please prepare your payment.',
                'priority' => 'urgent',
                'action_url' => '/loans',
                'is_read' => false,
            ],
            [
                'user_id' => $user->id,
                'type' => 'security_alert',
                'title' => 'New Login Detected',
                'message' => 'A new login was detected from your account. If this wasn\'t you, please change your password.',
                'priority' => 'high',
                'action_url' => '/profile/security',
                'is_read' => false,
            ],
            [
                'user_id' => $user->id,
                'type' => 'loan_approved',
                'title' => 'Loan Application Approved',
                'message' => 'Congratulations! Your loan application for ₱100,000.00 has been approved.',
                'priority' => 'high',
                'action_url' => '/loans',
                'is_read' => false,
            ],
            [
                'user_id' => $user->id,
                'type' => 'general',
                'title' => 'Welcome to the Cooperative',
                'message' => 'Thank you for joining our cooperative. We\'re here to help you achieve your financial goals.',
                'priority' => 'normal',
                'action_url' => '/dashboard',
                'is_read' => true,
                'read_at' => now()->subDays(2),
            ],
        ];

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }

        $this->command->info('Test notifications created successfully!');
    }
}

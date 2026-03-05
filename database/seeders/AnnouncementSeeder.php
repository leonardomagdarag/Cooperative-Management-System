<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        $announcements = [
            [
                'title' => 'Welcome to Our Cooperative!',
                'content' => 'We are thrilled to have you as part of our growing community. Our cooperative is committed to providing quality financial services and support to all our members. Together, we can achieve financial stability and prosperity.',
                'created_by' => $admin->id,
                'created_at' => now()->subDays(30),
            ],
            [
                'title' => 'Annual General Meeting - Save the Date',
                'content' => 'Our Annual General Meeting will be held on March 15, 2026, at the Community Center. All members are encouraged to attend. We will discuss financial reports, upcoming projects, and member benefits. Light refreshments will be served.',
                'created_by' => $admin->id,
                'created_at' => now()->subDays(20),
            ],
            [
                'title' => 'New Loan Products Available',
                'content' => 'We are excited to announce new loan products designed to meet your financial needs! Group loans, business loans, and special loans are now available with competitive interest rates. Visit our services section or contact our loan officers for more information.',
                'created_by' => $admin->id,
                'created_at' => now()->subDays(15),
            ],
            [
                'title' => 'Holiday Office Hours',
                'content' => 'Please note that our office will have adjusted hours during the upcoming holiday season. We will be closed on all public holidays but will maintain regular hours on other days. Emergency services remain available 24/7.',
                'created_by' => $admin->id,
                'created_at' => now()->subDays(10),
            ],
            [
                'title' => 'Member Education Workshop',
                'content' => 'Join us for a free financial literacy workshop on February 28, 2026! Topics include budgeting, savings strategies, and understanding loan terms. Space is limited, so please register early at the office.',
                'created_by' => $admin->id,
                'created_at' => now()->subDays(7),
            ],
            [
                'title' => 'Share Capital Increase Benefits',
                'content' => 'Members who maintain a share capital of ₱4,000 or more are now eligible for our premium business loan products with lower interest rates and higher loan amounts. Consider increasing your share capital to unlock these benefits!',
                'created_by' => $admin->id,
                'created_at' => now()->subDays(5),
            ],
            [
                'title' => 'Mobile Banking Coming Soon',
                'content' => 'We are working on launching our mobile banking application to make it easier for you to manage your accounts on the go. Stay tuned for more updates in the coming months!',
                'created_by' => $admin->id,
                'created_at' => now()->subDays(3),
            ],
            [
                'title' => 'Updated Operating Guidelines',
                'content' => 'We have updated our operating guidelines to better serve our members. Key changes include extended office hours on Fridays and improved loan processing times. Please review the complete guidelines available at our office.',
                'created_by' => $admin->id,
                'created_at' => now()->subDays(1),
            ],
        ];

        foreach ($announcements as $announcement) {
            Announcement::create($announcement);
        }
    }
}

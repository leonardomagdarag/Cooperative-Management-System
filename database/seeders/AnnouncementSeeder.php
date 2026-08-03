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
        $adminId = User::role('admin')->value('id')
            ?? User::query()->where('role', 'admin')->value('id');

        if (! $adminId) {
            $this->command?->warn('Skipping announcements: no admin user found.');

            return;
        }

        $announcements = [
            // ── Cooperative-internal announcements ────────────────────────
            [
                'title' => 'Welcome to Our Cooperative!',
                'content' => 'We are thrilled to have you as part of our growing community. Our cooperative is committed to providing quality financial services and support to all our members. Together, we can achieve financial stability and prosperity.',
                'type' => 'announcement',
                'visibility' => ['members'],
                'created_by' => $adminId,
                'created_at' => now()->subDays(30),
            ],
            [
                'title' => 'New Loan Products Available',
                'content' => 'We are excited to announce new loan products designed to meet your financial needs! Group loans, business loans, and special loans are now available with competitive interest rates. Visit our services section or contact our loan officers for more information.',
                'type' => 'announcement',
                'visibility' => ['members'],
                'created_by' => $adminId,
                'created_at' => now()->subDays(15),
            ],
            [
                'title' => 'Share Capital Increase Benefits',
                'content' => 'Members who maintain a share capital of ₱4,000 or more are now eligible for our premium business loan products with lower interest rates and higher loan amounts. Consider increasing your share capital to unlock these benefits!',
                'type' => 'announcement',
                'visibility' => ['members'],
                'created_by' => $adminId,
                'created_at' => now()->subDays(5),
            ],
            [
                'title' => 'Updated Operating Guidelines',
                'content' => 'We have updated our operating guidelines to better serve our members. Key changes include extended office hours on Fridays and improved loan processing times. Please review the complete guidelines available at our office.',
                'type' => 'announcement',
                'visibility' => ['members'],
                'created_by' => $adminId,
                'created_at' => now()->subDays(1),
            ],

            // ── Public news ───────────────────────────────────────────────
            [
                'title' => 'New Interest Rates for Savings Accounts',
                'content' => 'We\'re excited to announce increased interest rates on all savings accounts starting April 1, 2026. Regular savings accounts will now earn 3.5% annually, while time deposits will earn up to 5.5% depending on the term. This is part of our commitment to providing better returns for our members.',
                'type' => 'news',
                'visibility' => ['everyone'],
                'category' => 'Announcement',
                'created_by' => $adminId,
                'created_at' => now()->subDays(20),
            ],
            [
                'title' => 'Cooperative Reaches 1,000 Active Members',
                'content' => 'We\'re proud to announce that Settlers Credit Cooperative has reached a milestone of 1,000 active members! This achievement reflects our community\'s trust and commitment to cooperative principles. Thank you to all our members for being part of this journey.',
                'type' => 'news',
                'visibility' => ['everyone'],
                'category' => 'Achievement',
                'created_by' => $adminId,
                'created_at' => now()->subDays(10),
            ],
            [
                'title' => 'Mobile App Update: New Features Available',
                'content' => 'Our mobile banking app has been updated with new features including instant loan application status tracking, digital receipts, and enhanced security features. Update your app today to enjoy these improvements.',
                'type' => 'news',
                'visibility' => ['everyone'],
                'category' => 'Update',
                'created_by' => $adminId,
                'created_at' => now()->subDays(3),
            ],

            // ── Public events (upcoming) ──────────────────────────────────
            [
                'title' => 'Financial Literacy Workshop',
                'content' => 'Join us for an informative workshop on financial literacy, covering budgeting, saving, and investing tips. Open to all members and their families. Space is limited — register early at the office.',
                'type' => 'event',
                'visibility' => ['everyone'],
                'category' => 'Workshop',
                'event_date' => now()->addDays(48)->toDateString(),
                'event_time' => '9:00 AM',
                'event_location' => 'Main Office',
                'created_by' => $adminId,
            ],
            [
                'title' => 'Annual Member Meeting',
                'content' => 'All members are invited to discuss the cooperative\'s performance, future plans, and vote on important matters. Light refreshments will be served. Attendance is highly encouraged.',
                'type' => 'event',
                'visibility' => ['everyone'],
                'category' => 'Annual Meeting',
                'event_date' => now()->addDays(74)->toDateString(),
                'event_time' => '2:00 PM',
                'event_location' => 'Conference Hall',
                'created_by' => $adminId,
            ],
            [
                'title' => 'Community Outreach Program',
                'content' => 'Participate in our community outreach program aimed at supporting local initiatives and giving back to our barangay. Volunteers from member families are very welcome.',
                'type' => 'event',
                'visibility' => ['everyone'],
                'category' => 'Community Service',
                'event_date' => now()->addDays(100)->toDateString(),
                'event_time' => '8:00 AM',
                'event_location' => 'Community Center',
                'created_by' => $adminId,
            ],

            // ── Public events (past) ──────────────────────────────────────
            [
                'title' => 'Member Appreciation Day',
                'content' => 'We celebrated our members with fun activities, giveaways, and refreshments to show our appreciation for their continued loyalty and support to the cooperative.',
                'type' => 'event',
                'visibility' => ['everyone'],
                'category' => 'Celebration',
                'event_date' => now()->subDays(128)->toDateString(),
                'event_time' => '10:00 AM',
                'event_location' => 'Main Office',
                'created_by' => $adminId,
            ],
            [
                'title' => 'Holiday Savings Campaign',
                'content' => 'Our holiday campaign encouraged members to save more with special incentives and rewards for reaching savings goals. The campaign ran throughout December and was a huge success.',
                'type' => 'event',
                'visibility' => ['everyone'],
                'category' => 'Campaign',
                'event_date' => now()->subDays(180)->toDateString(),
                'event_time' => null,
                'event_location' => 'All Branches',
                'created_by' => $adminId,
            ],

            [
                'title' => 'New Loan Products Available',
                'content' => 'We are excited to announce new loan products designed to meet your financial needs! Group loans, business loans, and special loans are now available with competitive interest rates. Visit our services section or contact our loan officers for more information.',
                'type' => 'announcement',
                'visibility' => 'cooperative',
                'created_by' => $adminId,
                'created_at' => now()->subDays(15),
            ],
            [
                'title' => 'Share Capital Increase Benefits',
                'content' => 'Members who maintain a share capital of ₱4,000 or more are now eligible for our premium business loan products with lower interest rates and higher loan amounts. Consider increasing your share capital to unlock these benefits!',
                'type' => 'announcement',
                'visibility' => 'cooperative',
                'created_by' => $adminId,
                'created_at' => now()->subDays(5),
            ],
            [
                'title' => 'Updated Operating Guidelines',
                'content' => 'We have updated our operating guidelines to better serve our members. Key changes include extended office hours on Fridays and improved loan processing times. Please review the complete guidelines available at our office.',
                'type' => 'announcement',
                'visibility' => 'cooperative',
                'created_by' => $adminId,
                'created_at' => now()->subDays(1),
            ],

            // ── Public news ───────────────────────────────────────────────
            [
                'title' => 'New Interest Rates for Savings Accounts',
                'content' => 'We\'re excited to announce increased interest rates on all savings accounts starting April 1, 2026. Regular savings accounts will now earn 3.5% annually, while time deposits will earn up to 5.5% depending on the term. This is part of our commitment to providing better returns for our members.',
                'type' => 'news',
                'visibility' => 'public',
                'category' => 'Announcement',
                'created_by' => $adminId,
                'created_at' => now()->subDays(20),
            ],
            [
                'title' => 'Cooperative Reaches 1,000 Active Members',
                'content' => 'We\'re proud to announce that Settlers Credit Cooperative has reached a milestone of 1,000 active members! This achievement reflects our community\'s trust and commitment to cooperative principles. Thank you to all our members for being part of this journey.',
                'type' => 'news',
                'visibility' => 'public',
                'category' => 'Achievement',
                'created_by' => $adminId,
                'created_at' => now()->subDays(10),
            ],
            [
                'title' => 'Mobile App Update: New Features Available',
                'content' => 'Our mobile banking app has been updated with new features including instant loan application status tracking, digital receipts, and enhanced security features. Update your app today to enjoy these improvements.',
                'type' => 'news',
                'visibility' => 'public',
                'category' => 'Update',
                'created_by' => $adminId,
                'created_at' => now()->subDays(3),
            ],

            // ── Public events (upcoming) ──────────────────────────────────
            [
                'title' => 'Financial Literacy Workshop',
                'content' => 'Join us for an informative workshop on financial literacy, covering budgeting, saving, and investing tips. Open to all members and their families. Space is limited — register early at the office.',
                'type' => 'event',
                'visibility' => 'public',
                'category' => 'Workshop',
                'event_date' => now()->addDays(48)->toDateString(),
                'event_time' => '9:00 AM',
                'event_location' => 'Main Office',
                'created_by' => $adminId,
            ],
            [
                'title' => 'Annual Member Meeting',
                'content' => 'All members are invited to discuss the cooperative\'s performance, future plans, and vote on important matters. Light refreshments will be served. Attendance is highly encouraged.',
                'type' => 'event',
                'visibility' => 'public',
                'category' => 'Annual Meeting',
                'event_date' => now()->addDays(74)->toDateString(),
                'event_time' => '2:00 PM',
                'event_location' => 'Conference Hall',
                'created_by' => $adminId,
            ],
            [
                'title' => 'Community Outreach Program',
                'content' => 'Participate in our community outreach program aimed at supporting local initiatives and giving back to our barangay. Volunteers from member families are very welcome.',
                'type' => 'event',
                'visibility' => 'public',
                'category' => 'Community Service',
                'event_date' => now()->addDays(100)->toDateString(),
                'event_time' => '8:00 AM',
                'event_location' => 'Community Center',
                'created_by' => $adminId,
            ],

            // ── Public events (past) ──────────────────────────────────────
            [
                'title' => 'Member Appreciation Day',
                'content' => 'We celebrated our members with fun activities, giveaways, and refreshments to show our appreciation for their continued loyalty and support to the cooperative.',
                'type' => 'event',
                'visibility' => 'public',
                'category' => 'Celebration',
                'event_date' => now()->subDays(128)->toDateString(),
                'event_time' => '10:00 AM',
                'event_location' => 'Main Office',
                'created_by' => $adminId,
            ],
            [
                'title' => 'Holiday Savings Campaign',
                'content' => 'Our holiday campaign encouraged members to save more with special incentives and rewards for reaching savings goals. The campaign ran throughout December and was a huge success.',
                'type' => 'event',
                'visibility' => 'public',
                'category' => 'Campaign',
                'event_date' => now()->subDays(180)->toDateString(),
                'event_time' => null,
                'event_location' => 'All Branches',
                'created_by' => $adminId,
            ],
        ];

        foreach ($announcements as $announcement) {
            Announcement::create($announcement);
        }
    }
}

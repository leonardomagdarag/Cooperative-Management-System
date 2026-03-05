<?php

namespace Database\Seeders;

use App\Models\SiteContent;
use Illuminate\Database\Seeder;

class SiteContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contents = [
            // HOME SECTION
            [
                'section' => 'home',
                'key' => 'hero_subtitle',
                'value' => 'Homing <span class="font-bold text-primary2">Strength</span> in members through financial excellence and community partnership',
                'type' => 'html',
                'description' => 'Hero section subtitle with HTML formatting',
                'order' => 1,
            ],
            [
                'section' => 'home',
                'key' => 'active_members',
                'value' => '567',
                'type' => 'number',
                'description' => 'Number of active members',
                'order' => 2,
            ],
            [
                'section' => 'home',
                'key' => 'total_assets',
                'value' => '₱5M+',
                'type' => 'text',
                'description' => 'Total cooperative assets',
                'order' => 3,
            ],
            [
                'section' => 'home',
                'key' => 'years_service',
                'value' => '15',
                'type' => 'number',
                'description' => 'Years of service',
                'order' => 4,
            ],
            [
                'section' => 'home',
                'key' => 'service_done',
                'value' => '10k+',
                'type' => 'text',
                'description' => 'Services completed',
                'order' => 5,
            ],

            // ABOUT SECTION
            [
                'section' => 'about',
                'key' => 'about1',
                'value' => 'Settlers Credit Cooperative is dedicated to empowering our members through financial services and community support. Our mission is to provide accessible and affordable financial solutions that help our members achieve their goals.',
                'type' => 'text',
                'description' => 'About paragraph 1',
                'order' => 1,
            ],
            [
                'section' => 'about',
                'key' => 'about2',
                'value' => "Founded with a vision to strengthen our community, we've been serving our members with integrity, innovation, and unwavering commitment to their financial well-being.",
                'type' => 'text',
                'description' => 'About paragraph 2',
                'order' => 2,
            ],
            [
                'section' => 'about',
                'key' => 'history',
                'value' => 'Founded in 1995, Settlers Credit Cooperative has grown from a small group of community members into a trusted financial institution serving thousands of members. <br><br> Over the years, we have expanded our services and strengthened our commitment to member satisfaction and community development. Our journey reflects our dedication to financial excellence and community empowerment.',
                'type' => 'html',
                'description' => 'Cooperative history',
                'order' => 3,
            ],
            [
                'section' => 'about',
                'key' => 'mission',
                'value' => 'To provide quality financial services that meet the needs of our members while promoting economic growth and community development. We strive to empower every member with the tools and support they need to achieve financial success.',
                'type' => 'text',
                'description' => 'Mission statement',
                'order' => 4,
            ],
            [
                'section' => 'about',
                'key' => 'vision',
                'value' => 'To be the leading credit cooperative recognized for excellence in service, innovation, and member satisfaction. We envision a future where every member thrives financially and contributes to a stronger community.',
                'type' => 'text',
                'description' => 'Vision statement',
                'order' => 5,
            ],
            [
                'section' => 'about',
                'key' => 'values',
                'value' => json_encode([
                    'Integrity' => 'We uphold honesty and transparency in all our dealings',
                    'Excellence' => 'We strive for the highest standards in service delivery',
                    'Community' => 'We prioritize the collective welfare of our members',
                    'Innovation' => 'We embrace change and continuously improve our services',
                ]),
                'type' => 'json',
                'description' => 'Core values (can be edited in admin)',
                'order' => 6,
            ],

            // EVENTS SECTION
            [
                'section' => 'events',
                'key' => 'description',
                'value' => 'Stay updated with the latest events and activities organized by Settlers Credit Cooperative. We are committed to fostering community engagement and providing opportunities for our members to connect and grow.',
                'type' => 'text',
                'description' => 'Events page description',
                'order' => 1,
            ],

            // CONTACT SECTION
            [
                'section' => 'contact',
                'key' => 'address',
                'value' => 'Sunlife Learning Center, Site 3, Brgy Sto Tomas, Calauan, Laguna, Philippines',
                'type' => 'text',
                'description' => 'Office address',
                'order' => 1,
            ],
            [
                'section' => 'contact',
                'key' => 'phone',
                'value' => '09876543210 / 09123456789',
                'type' => 'text',
                'description' => 'Contact phone numbers',
                'order' => 2,
            ],
            [
                'section' => 'contact',
                'key' => 'email',
                'value' => 'settlerscoop@email.com',
                'type' => 'text',
                'description' => 'Contact email',
                'order' => 3,
            ],
            [
                'section' => 'contact',
                'key' => 'office_hours',
                'value' => 'Mon - Fri: 8:00 AM - 5:00 PM <br> Sat: 8:00 AM - 12:00 PM',
                'type' => 'html',
                'description' => 'Office hours',
                'order' => 4,
            ],
        ];

        foreach ($contents as $content) {
            SiteContent::updateOrCreate(
                [
                    'section' => $content['section'],
                    'key' => $content['key'],
                ],
                $content
            );
        }

        $this->command->info('Site content seeded successfully!');
    }
}

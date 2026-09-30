<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Notices on the cooperative's bulletin board.
 *
 * Only the visibility values the announcement form accepts are used, and every
 * notice is stored once so the board does not show the same item twice. The
 * figures quoted are the ones this cooperative actually works with, taken from
 * the seeded loan products and share-capital requirement.
 */
class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $authorId = User::role('admin')->value('id')
            ?? User::query()->where('role', 'admin')->value('id');

        if (! $authorId) {
            $this->command?->warn('Skipping announcements: no admin user found.');

            return;
        }

        $notices = [
            // ── Notices to members ────────────────────────────────────────
            [
                'title' => 'Share Capital Requirement for Business Loans',
                'content' => 'A business loan now requires a share capital of ₱4,000 and at least one year of membership. '
                    .'Members who want to upgrade may pay the difference over the counter at either branch, and the loan '
                    .'officer will re-assess the application once the share capital is recorded.',
                'type' => Announcement::TYPE_ANNOUNCEMENT,
                'visibility' => [Announcement::VISIBILITY_MEMBERS],
                'created_at' => now()->subDays(45),
            ],
            [
                'title' => 'Group Lending: Four Co-makers Required',
                'content' => 'A group loan of up to ₱5,000 over six months needs four co-makers who are members in good '
                    .'standing. All five names appear on the same promissory note, so please confirm the list with the '
                    .'loan officer before signing.',
                'type' => Announcement::TYPE_ANNOUNCEMENT,
                'visibility' => [Announcement::VISIBILITY_MEMBERS],
                'created_at' => now()->subDays(38),
            ],
            [
                'title' => 'Passbook Update and Old Passbook Turnover',
                'content' => 'The ledger is being reconciled from January onwards. Please bring your old passbook to the '
                    .'branch so the teller can stamp and update it. Passbooks left unstamped for more than thirty days '
                    .'will be released for a full statement instead.',
                'type' => Announcement::TYPE_ANNOUNCEMENT,
                'visibility' => [Announcement::VISIBILITY_MEMBERS],
                'created_at' => now()->subDays(22),
            ],
            [
                'title' => 'Instalment Schedule Is Now Printed on Every Release',
                'content' => 'Every loan release now comes with the amortised schedule showing the amount due per month '
                    .'and the date each instalment falls due. Keep a copy and check it against your payments so a missed '
                    .'month does not become a penalty case.',
                'type' => Announcement::TYPE_ANNOUNCEMENT,
                'visibility' => [Announcement::VISIBILITY_MEMBERS],
                'created_at' => now()->subDays(9),
            ],
            [
                'title' => 'Office Hours During the Rice Season',
                'content' => 'From planting to harvest the counter closes at four in the afternoon on weekdays so the '
                    .'staff can attend to the fields. Saturday service remains open from eight to twelve at the Main Office.',
                'type' => Announcement::TYPE_ANNOUNCEMENT,
                'visibility' => [Announcement::VISIBILITY_MEMBERS, Announcement::VISIBILITY_OFFICERS],
                'created_at' => now()->subDays(3),
            ],
            [
                'title' => 'Loan Accounts With Pending Requests',
                'content' => 'If you filed a loan request and have not been called, the credit committee reviews '
                    .'applications once a week. Requests still awaiting interview are marked pending until a slot opens; '
                    .'no need to file again, and a second request does not move your application forward.',
                'type' => Announcement::TYPE_ANNOUNCEMENT,
                'visibility' => [Announcement::VISIBILITY_MEMBERS],
                'created_at' => now()->subDay(),
            ],

            // ── Public news ───────────────────────────────────────────────
            [
                'title' => 'Cooperative Secures CDA Registration Renewal',
                'content' => 'The registration of Settlers Credit Cooperative with the Cooperative Development Authority '
                    .'has been renewed for the current term. The renewed registration is posted at the Main Office, and '
                    .'members may inspect it during office hours.',
                'type' => Announcement::TYPE_NEWS,
                'category' => 'Registration',
                'visibility' => [Announcement::VISIBILITY_PUBLIC, Announcement::VISIBILITY_MEMBERS],
                'created_at' => now()->subDays(60),
            ],
            [
                'title' => 'Branch Office Opens in Barangay Prizon',
                'content' => 'To bring the service closer to the members living east of the national highway, a collection '
                    .'point now operates in Prizon on Wednesdays and Fridays. Loan releases remain at the Main Office; '
                    .'deposits, withdrawals and passbook updates can be settled at the branch.',
                'type' => Announcement::TYPE_NEWS,
                'category' => 'Branch',
                'visibility' => [Announcement::VISIBILITY_PUBLIC, Announcement::VISIBILITY_MEMBERS],
                'created_at' => now()->subDays(16),
            ],
            [
                'title' => 'Patrone Dividend Approved for Last Year',
                'content' => 'The general assembly approved the distribution of last year\'s surplus in proportion to the '
                    .'share capital held. The dividend is credited against each member\'s share account, so no member '
                    .'needs to file a claim; the teller can show the entry on the passbook.',
                'type' => Announcement::TYPE_NEWS,
                'category' => 'Patronage',
                'visibility' => [Announcement::VISIBILITY_PUBLIC, Announcement::VISIBILITY_MEMBERS],
                'created_at' => now()->subDays(6),
            ],

            // ── Upcoming events ───────────────────────────────────────────
            [
                'title' => 'Financial Literacy Seminar on Budgeting',
                'content' => 'A half-day session on keeping a household budget, separating consumption from capital, and '
                    .'avoiding borrowing at high interest. Open to members and their families; please register at the '
                    .'counter because the hall seats forty.',
                'type' => Announcement::TYPE_EVENT,
                'category' => 'Seminar',
                'event_date' => now()->addDays(23)->toDateString(),
                'event_time' => '9:00 AM',
                'event_location' => 'Main Office, Barangay Mabacan',
                'visibility' => [Announcement::VISIBILITY_PUBLIC, Announcement::VISIBILITY_MEMBERS],
            ],
            [
                'title' => 'General Assembly',
                'content' => 'All members are invited to the general assembly. Agenda: the financial statement, the report '
                    .'of the credit committee, the proposed amendment to the bylaws on the minimum share capital, and the '
                    .'election of two committee members whose terms end this year.',
                'type' => Announcement::TYPE_EVENT,
                'category' => 'Assembly',
                'event_date' => now()->addDays(67)->toDateString(),
                'event_time' => '1:00 PM',
                'event_location' => 'Main Office, Barangay Mabacan',
                'visibility' => [Announcement::VISIBILITY_PUBLIC, Announcement::VISIBILITY_MEMBERS],
            ],
            [
                'title' => 'Barangay Clean-Up Drive',
                'content' => 'The cooperative is joining the barangay clean-up drive. Volunteers from member families are '
                    .'welcome; the backhoe and the drinking water are provided by the cooperative.',
                'type' => Announcement::TYPE_EVENT,
                'category' => 'Community',
                'event_date' => now()->addDays(95)->toDateString(),
                'event_time' => '6:30 AM',
                'event_location' => 'Barangay Hall, Mabacan',
                'visibility' => [Announcement::VISIBILITY_PUBLIC, Announcement::VISIBILITY_MEMBERS],
            ],

            // ── Past events ───────────────────────────────────────────────
            [
                'title' => 'Member Appreciation Day',
                'content' => 'The cooperative held its appreciation day for long-serving members, with a short programme '
                    .'at the Main Office and a shared meal afterwards. Members who attended received their certificates '
                    .'of recognition.',
                'type' => Announcement::TYPE_EVENT,
                'category' => 'Celebration',
                'event_date' => now()->subDays(140)->toDateString(),
                'event_time' => '10:00 AM',
                'event_location' => 'Main Office, Barangay Mabacan',
                'visibility' => [Announcement::VISIBILITY_PUBLIC, Announcement::VISIBILITY_MEMBERS],
            ],
            [
                'title' => 'Feast for the Patron',
                'content' => 'Members and their families came together for the feast that closes the harvest season, held '
                    .'at the Main Office. Thank you to everyone who prepared, cooked and cleaned up.',
                'type' => Announcement::TYPE_EVENT,
                'category' => 'Celebration',
                'event_date' => now()->subDays(198)->toDateString(),
                'event_time' => '11:00 AM',
                'event_location' => 'Main Office, Barangay Mabacan',
                'visibility' => [Announcement::VISIBILITY_PUBLIC, Announcement::VISIBILITY_MEMBERS],
            ],
        ];

        foreach ($notices as $notice) {
            Announcement::updateOrCreate(
                ['title' => $notice['title']],
                $notice + ['created_by' => $authorId],
            );
        }

        $this->command?->info('Published '.count($notices).' notices.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\BoardMember;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The elected board of the cooperative.
 *
 * Four members of the seeded roster sit on the board, and the chairperson and
 * secretary are the same people the UserSeeder makes officers, so a name that
 * appears in the officer list is the same person here. Terms are dated to a
 * current three-year cycle and the mottos are the members' own, in the Taglish
 * that members of a Calauan cooperative actually speak.
 */
class BoardMemberSeeder extends Seeder
{
    /** The current board term. */
    private const TERM_START = '2025-01-01';

    private const TERM_END = '2027-12-31';

    public function run(): void
    {
        $board = [
            [
                'user_number' => 'USR000008',
                'position' => 'chairperson',
                'title' => 'Chairperson',
                'motto' => "<i>'Boku ang kagandahan ng Calauan.'</i>",
                'bio' => 'Rice trader and founding member. Chairs the general assembly and leads the credit committee.',
                'order' => 1,
            ],
            [
                'user_number' => 'USR000011',
                'position' => 'vice_chairperson',
                'title' => 'Vice Chairperson',
                'motto' => "<i>'Kung may tinutulong, may kailangan.'</i>",
                'bio' => 'Sari-sari store owner. Deputises for the chairperson and oversees the member services committee.',
                'order' => 2,
            ],
            [
                'user_number' => 'USR000009',
                'position' => 'treasurer',
                'title' => 'Treasurer',
                'motto' => "<i>'Bawat piso, may record. Walang tanan.'</i>",
                'bio' => 'Records the cooperative\'s books and reports the monthly financial statement to the board.',
                'order' => 3,
            ],
            [
                'user_number' => 'USR000014',
                'position' => 'secretary',
                'title' => 'Secretary',
                'motto' => "<i>'Sundan lang ang papeles. Ako na bahala.'</i>",
                'bio' => 'Keeps the minutes, the member registry and the correspondence of the cooperative.',
                'order' => 4,
            ],
            [
                'user_number' => 'USR000007',
                'position' => 'auditor',
                'title' => 'Auditor',
                'motto' => "<i>'Lahat ng entry, may basehang papel.'</i>",
                'bio' => 'Voluntarily audits the cash collections and the passbook entries each quarter.',
                'order' => 5,
            ],
        ];

        foreach ($board as $entry) {
            $user = User::query()->where('user_number', $entry['user_number'])->first();

            $attributes = [
                'name' => $user?->name ?? $entry['user_number'],
                'title' => $entry['title'],
                'motto' => $entry['motto'],
                'bio' => $entry['bio'],
                'email' => $user?->email,
                'phone' => $user?->personalData?->contact?->phone_no,
                'term_start' => self::TERM_START,
                'term_end' => self::TERM_END,
                'order' => $entry['order'],
                'is_active' => true,
            ];

            BoardMember::updateOrCreate(['position' => $entry['position']], $attributes);
        }

        $this->command?->info('Seated '.count($board).' board members for the '.self::TERM_START.' to '.self::TERM_END.' term.');
    }
}

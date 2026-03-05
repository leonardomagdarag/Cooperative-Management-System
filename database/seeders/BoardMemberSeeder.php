<?php

namespace Database\Seeders;

use App\Models\BoardMember;
use Illuminate\Database\Seeder;

class BoardMemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $boardMembers = [
            [
                'name' => 'Cris David Jarabejo',
                'position' => 'chairperson',
                'title' => 'Chairperson',
                'motto' => "<i>'Boku no Pico 10/10. Higly Recommended'</i>",
                'order' => 1,
                'term_start' => '2024-01-01',
                'term_end' => '2025-12-31',
            ],
            [
                'name' => 'Jean Leslie Velasco',
                'position' => 'vice_chairperson',
                'title' => 'Vice Chairperson',
                'motto' => "<i>'Rank 5 Vexana in Calauan'</i>",
                'order' => 2,
                'term_start' => '2024-01-01',
                'term_end' => '2025-12-31',
            ],
            [
                'name' => 'Glendo Camarines',
                'position' => 'treasurer',
                'title' => 'Treasurer',
                'motto' => "<i>'Wait palit lang account baka mabawasan star.'</i>",
                'order' => 3,
                'term_start' => '2024-01-01',
                'term_end' => '2025-12-31',
            ],
            [
                'name' => 'Alyson Penano',
                'position' => 'secretary',
                'title' => 'Secretary',
                'motto' => "<i>'Ako na naman. Nanahimik yung tao.'</i>",
                'order' => 4,
                'term_start' => '2024-01-01',
                'term_end' => '2025-12-31',
            ],
        ];

        foreach ($boardMembers as $member) {
            BoardMember::updateOrCreate(
                ['position' => $member['position']],
                $member
            );
        }

        $this->command->info('Board members seeded successfully!');
    }
}

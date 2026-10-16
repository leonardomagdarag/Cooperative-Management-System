<?php

namespace Database\Seeders;

use App\Models\Branch;
use Database\Seeders\Support\MemberRoster;
use Illuminate\Database\Seeder;

/**
 * The two offices the cooperative operates.
 *
 * The head office sits in Mabacan, the barangay the member roster treats as the
 * centre of the membership, and the second office was opened to reach the
 * members east of the national highway. Addresses follow the same PSGC data
 * the members' addresses use.
 */
class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $municipality = MemberRoster::MUNICIPALITY;

        $branches = [
            [
                'code' => 'BR000001',
                'name' => 'Main',
                'is_head_office' => true,
                'barangay' => 'Mabacan',
                'street' => 'Purok 3, Sitio Maligaya',
                'contact_number' => '(049) 564-2210',
                'email' => 'mainoffice@settlerscredit.coop',
            ],
            [
                'code' => 'BR000002',
                'name' => 'Branch Office',
                'is_head_office' => false,
                'barangay' => 'Prinza',
                'street' => 'Barangay Hall, Prinza',
                'contact_number' => '(049) 564-2230',
                'email' => 'branchoffice@settlerscredit.coop',
            ],
        ];

        foreach ($branches as $data) {
            $address = sprintf(
                '%s, %s, %s, %s',
                $data['street'],
                $data['barangay'],
                $municipality['city_municipality'],
                $municipality['province'],
            );

            Branch::query()->withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'is_head_office' => $data['is_head_office'],
                    'address' => $address,
                    'contact_number' => $data['contact_number'],
                    'email' => $data['email'],
                    'is_active' => true,
                ]
            );
        }
    }
}

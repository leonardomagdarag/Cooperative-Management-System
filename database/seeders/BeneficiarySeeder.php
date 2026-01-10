<?php

namespace Database\Seeders;

use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Database\Seeder;

class BeneficiarySeeder extends Seeder
{
    public function run(): void
    {
        // Get all users with role 'member'
        $members = User::where('role', 'member')->get();

        // Create 1-3 beneficiaries for 60% of members
        foreach ($members->random(min(15, $members->count())) as $member) {
            Beneficiary::factory(rand(1, 3))->create([
                'user_id' => $member->id,
            ]);
        }
    }
}

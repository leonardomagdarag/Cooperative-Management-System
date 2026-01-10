<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ===================================
        // CORE ACCOUNTS (Keep for testing)
        // ===================================

        // Create developer account
        User::create([
            'user_number' => 'USR000001',
            'personal_data_id' => PersonalData::factory()->create([
                'first_name' => 'Leo',
                'last_name' => 'Magdarag',
                'birth_date' => '2000-01-01',
                'title' => 'Member',
            ])->id,
            'email' => 'developer@example.com',
            'password' => Hash::make('password'),
            'role' => 'developer',
        ]);

        // Create an admin user
        User::create([
            'user_number' => 'USR000002',
            'personal_data_id' => PersonalData::factory()->create([
                'first_name' => 'Admin',
                'last_name' => 'Account',
                'birth_date' => '1985-01-01',
                'title' => 'Chairman',
            ])->id,
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Create additional staff accounts
        $roles = [
            ['role' => 'treasurer', 'firstName' => 'Maria', 'lastName' => 'Santos', 'title' => 'Treasurer'],
            ['role' => 'secretary', 'firstName' => 'Ana', 'lastName' => 'Garcia', 'title' => 'Secretary'],
            ['role' => 'loan', 'firstName' => 'Pedro', 'lastName' => 'Reyes', 'title' => 'Loan Officer'],
        ];

        foreach ($roles as $index => $roleData) {
            User::create([
                'user_number' => 'USR' . str_pad($index + 3, 6, '0', STR_PAD_LEFT),
                'personal_data_id' => PersonalData::factory()->create([
                    'first_name' => $roleData['firstName'],
                    'last_name' => $roleData['lastName'],
                    'title' => $roleData['title'],
                ])->id,
                'email' => strtolower($roleData['role']) . '@example.com',
                'password' => Hash::make('password'),
                'role' => $roleData['role'],
            ]);
        }

        // ===================================
        // GENERATE 20 MEMBER USERS (Factory)
        // ===================================
        User::factory(20)->create(['role' => 'member']);
    }
}

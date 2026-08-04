<?php

namespace Database\Seeders;

use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private function createSeedUser(array $attributes, array $personalData): User
    {
        // Seeders may be run more than once during local development. Reuse the
        // fixed account instead of creating another row with the same email.
        $existing = User::query()->where('email', $attributes['email'])->first();

        if ($existing) {
            return $existing;
        }

        $attributes['personal_data_id'] = PersonalData::factory()->create($personalData)->id;

        $user = User::create($attributes);

        // Seed logins are verified by default so demo accounts can access the app.
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function buildMemberEmail(string $firstName, string $lastName): string
    {
        $normalizedFirstName = strtolower((string) preg_replace('/\s+/', '', trim($firstName)));
        $normalizedLastName = strtolower(trim($lastName));

        return "{$normalizedFirstName}.{$normalizedLastName}@example.com";
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ===================================
        // CORE ACCOUNTS (Keep for testing)
        // ===================================

        // Create developer account
        $developer = $this->createSeedUser([
            'user_number' => 'USR000001',
            'email' => 'developer@example.com',
            'password' => Hash::make('password'),
            'role' => 'developer',
        ], [
            'first_name' => 'Leo',
            'last_name' => 'Magdarag',
            'birth_date' => '2000-01-01',
            'title' => 'Member',
        ]);
        $developer->assignRole('developer');

        // Create one seeded user for each officer role.
        $officers = [
            ['role' => 'admin', 'firstName' => 'Quennie Joy', 'lastName' => 'Almazan', 'title' => 'Chairman', 'userNumber' => 'USR000002', 'birthDate' => '1985-01-01'],
            ['role' => 'manager', 'firstName' => 'Carl Michael', 'lastName' => 'Bigal', 'title' => 'Vice Chairman', 'userNumber' => 'USR000003'],
            ['role' => 'accountant', 'firstName' => 'Jasmin', 'lastName' => 'Latayan', 'title' => 'Accountant', 'userNumber' => 'USR000004'],
            ['role' => 'loan', 'firstName' => 'Leanard', 'lastName' => 'Guiriba', 'title' => 'Loan Officer', 'userNumber' => 'USR000005'],
            ['role' => 'secretary', 'firstName' => 'Jenica', 'lastName' => 'Malveda', 'title' => 'Secretary', 'userNumber' => 'USR000006'],
        ];

        foreach ($officers as $officerData) {
            $personalData = [
                'first_name' => $officerData['firstName'],
                'last_name' => $officerData['lastName'],
                'title' => $officerData['title'],
            ];

            if (isset($officerData['birthDate'])) {
                $personalData['birth_date'] = $officerData['birthDate'];
            }

            $officer = $this->createSeedUser([
                'user_number' => $officerData['userNumber'],
                'email' => strtolower($officerData['role']).'@example.com',
                'password' => Hash::make('password'),
                'role' => $officerData['role'],
                'is_officer' => true,
            ], $personalData);
            $officer->assignRole($officerData['role']);
        }

        // ===================================
        // GENERATE MEMBER USERS
        // ===================================

        $members = [
            ['userNumber' => 'USR000007', 'firstName' => 'Arjay', 'lastName' => 'Ramos'],
            ['userNumber' => 'USR000008', 'firstName' => 'Cris David', 'lastName' => 'Jarabejo'],
            ['userNumber' => 'USR000009', 'firstName' => 'Glendo', 'lastName' => 'Camarines'],
            ['userNumber' => 'USR000010', 'firstName' => 'Jerrelyn', 'lastName' => 'Amper'],
            ['userNumber' => 'USR000011', 'firstName' => 'Jean Leslie', 'lastName' => 'Velasco'],
            ['userNumber' => 'USR000012', 'firstName' => 'Jeral Jean', 'lastName' => 'Cabillon'],
            ['userNumber' => 'USR000013', 'firstName' => 'Leo Angelo', 'lastName' => 'Centeno'],
            ['userNumber' => 'USR000014', 'firstName' => 'Alyson', 'lastName' => 'Penano'],
            ['userNumber' => 'USR000015', 'firstName' => 'Ravie', 'lastName' => 'Premor'],
            [
                'userNumber' => 'USR000016',
                'firstName' => 'Leo',
                'lastName' => 'Magdarag',
                'midName' => 'Baluyot',
                'birthDate' => '1995-06-15',
                'email' => 'leo.baluyot.magdarag039@gmail.com',
            ],
        ];

        foreach ($members as $memberData) {
            $personalData = [
                'first_name' => $memberData['firstName'],
                'last_name' => $memberData['lastName'],
                'title' => 'Member',
            ];

            if (isset($memberData['midName'])) {
                $personalData['mid_name'] = $memberData['midName'];
            }

            if (isset($memberData['birthDate'])) {
                $personalData['birth_date'] = $memberData['birthDate'];
            }

            $member = $this->createSeedUser([
                'user_number' => $memberData['userNumber'],
                'email' => $memberData['email'] ?? $this->buildMemberEmail($memberData['firstName'], $memberData['lastName']),
                'password' => Hash::make('password'),
                'role' => 'member',
            ], $personalData);
            $member->assignRole('member');
        }
    }
}

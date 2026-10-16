<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\Employment;
use App\Models\MemberId;
use App\Models\PersonalData;
use App\Models\User;
use Database\Seeders\Support\MemberRoster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Creates the fixed demo logins and the ten rostered members.
 *
 * Member identity is not invented here — it comes from MemberRoster so the
 * seeders that run afterwards (accounts, loans, transactions) see the same
 * people, the same join dates and the same branch assignments.
 */
class UserSeeder extends Seeder
{
    /**
     * Create (or refresh) a user together with its full personal profile.
     *
     * Seeders are routinely re-run during local development, so the fixed
     * email is the identity key: an existing account is reused and its
     * supporting profile rows are updated in place rather than duplicated.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $profile
     */
    private function createSeedUser(array $attributes, array $profile): User
    {
        $email = User::normalizeEmail($attributes['email']);
        $existing = User::query()->where('email', $email)->first();

        $personalDataId = $this->syncProfile($existing?->personalData, $email, $profile);

        if ($existing) {
            $changes = [
                'personal_data_id' => $personalDataId,
                'branch_id' => $attributes['branch_id'] ?? $existing->branch_id,
                'role' => $attributes['role'] ?? $existing->role,
            ];

            foreach (['special_loan_eligible', 'profile_completed_at', 'is_officer'] as $flag) {
                if (array_key_exists($flag, $attributes)) {
                    $changes[$flag] = $attributes[$flag];
                }
            }

            $existing->forceFill($changes)->save();

            return $existing;
        }

        return User::withoutTimestamps(function () use ($attributes, $personalDataId) {
            $user = new User;
            $user->forceFill(array_merge($attributes, ['personal_data_id' => $personalDataId]));
            $user->email_verified_at = now();
            $user->created_at = $attributes['created_at'] ?? now();
            $user->updated_at = $user->created_at;
            $user->save();

            return $user;
        });
    }

    /**
     * Create or refresh the address/contact/employment/ID rows behind a profile.
     *
     * Each of those tables is one-to-one with a person — personal_data holds a
     * unique foreign key to each of them — so rows are updated in place rather
     * than looked up by content, otherwise two members living in the same
     * barangay would end up sharing one address.
     *
     * @param  array<string, mixed>  $profile
     */
    private function syncProfile(?PersonalData $existing, string $email, array $profile): int
    {
        $isLeonardoMagdarag = $email === 'leo.baluyot.magdarag039@gmail.com';

        $address = $existing?->address ?? new Address;
        $address->fill(array_merge(MemberRoster::MUNICIPALITY, [
            'barangay' => $profile['barangay'],
            'street' => $profile['street'],
        ]));
        $address->save();

        $contact = $existing?->contact ?? new Contact;
        $contact->fill([
            'phone_no' => $isLeonardoMagdarag ? '09761729700' : $profile['phone'],
            'phone_verified_at' => $isLeonardoMagdarag ? now() : null,
            'email' => $email,
            'email_verified_at' => now(),
        ]);
        $contact->save();

        $employment = $existing?->employment ?? new Employment;
        $employment->fill($profile['employment']);
        $employment->save();

        $memberId = $existing?->memberId ?? new MemberId;
        $memberId->fill($profile['identifiers']);
        $memberId->save();

        $personalData = $existing ?? new PersonalData;
        $personalData->fill([
            'title' => $profile['title'] ?? 'Member',
            'first_name' => $profile['first_name'],
            'mid_name' => $profile['mid_name'] ?? null,
            'last_name' => $profile['last_name'],
            'birth_date' => $profile['birth_date'] ?? null,
            'birthplace' => $profile['birthplace'] ?? null,
            'gender' => $profile['gender'] ?? null,
            'education' => $profile['education'] ?? null,
            'civil_status' => $profile['civil_status'] ?? null,
            'religion' => $profile['religion'] ?? null,
            'profile_picture' => 'placeholders/dummy_profile.svg',
            'address_id' => $address->id,
            'contact_id' => $contact->id,
            'employment_id' => $employment->id,
            'member_id' => $memberId->id,
        ]);
        $personalData->save();

        return $personalData->id;
    }

    /**
     * Derive a stable digit string from a seed.
     *
     * Government ID numbers have to look real, but they must not change on
     * every re-seed, so they are derived from the member's user number rather
     * than randomised.
     */
    private function digitsFrom(string $seed, int $length): string
    {
        $digits = '';
        $hash = abs(crc32($seed));

        while (strlen($digits) < $length) {
            $hash = abs(crc32($hash.'|'.$seed));
            $digits .= (string) ($hash % 10);
        }

        return substr($digits, 0, $length);
    }

    /**
     * @param  list<int>  $sizes
     */
    private function groupDigits(string $digits, array $sizes): string
    {
        $parts = [];
        $offset = 0;

        foreach ($sizes as $size) {
            $parts[] = substr($digits, $offset, $size);
            $offset += $size;
        }

        return implode('-', $parts);
    }

    /**
     * @return array<string, string>
     */
    private function governmentIdentifiers(string $userNumber, string $barangay): array
    {
        return [
            'national_id' => $this->groupDigits($this->digitsFrom($userNumber.'|national', 16), [4, 4, 4, 4]),
            'national_id_photo' => 'placeholders/dummy_id.svg',
            'tin' => $this->groupDigits($this->digitsFrom($userNumber.'|tin', 12), [3, 3, 3, 3]),
            'tin_photo' => 'placeholders/dummy_id.svg',
            'sss' => $this->groupDigits($this->digitsFrom($userNumber.'|sss', 10), [2, 7, 1]),
            'sss_photo' => 'placeholders/dummy_id.svg',
            'philhealth' => $this->groupDigits($this->digitsFrom($userNumber.'|philhealth', 12), [2, 9, 1]),
            'philhealth_photo' => 'placeholders/dummy_id.svg',
            'barangay_id' => MemberRoster::barangayCode($barangay) ?? '',
            'barangay_id_photo' => 'placeholders/dummy_id.svg',
            'proof_of_registration_path' => 'placeholders/dummy_id.svg',
            'other_document_path' => 'placeholders/dummy_id.svg',
        ];
    }

    private function buildMemberEmail(string $firstName, string $lastName): string
    {
        $normalizedFirstName = strtolower((string) preg_replace('/\s+/', '', trim($firstName)));
        $normalizedLastName = strtolower(trim($lastName));

        return "leo.baluyot039+{$normalizedFirstName}.{$normalizedLastName}@gmail.com";
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Storage::disk('public')->exists('placeholders/dummy_id.svg')) {
            Storage::disk('public')->put('placeholders/dummy_id.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150"><rect width="200" height="150" fill="#ddd"/><text x="100" y="75" font-family="sans-serif" font-size="20" text-anchor="middle" fill="#555">ID Document</text></svg>');
        }
        if (! Storage::disk('public')->exists('placeholders/dummy_profile.svg')) {
            Storage::disk('public')->put('placeholders/dummy_profile.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="#ddd"/><circle cx="100" cy="80" r="40" fill="#999"/><path d="M40 180 q 60 -80 120 0" stroke="#999" stroke-width="10" fill="none"/></svg>');
        }

        // Branch records must exist before users can be tied to them. Keeping
        // the call here lets feature tests seed UserSeeder directly.
        $this->call(BranchSeeder::class);

        $branches = Branch::query()->whereIn('code', ['BR000001', 'BR000002'])->get()->keyBy('code');

        if ($branches->count() !== 2) {
            throw new \RuntimeException('BranchSeeder must run before UserSeeder.');
        }

        $mainBranch = $branches['BR000001'];
        // ===================================
        // CORE ACCOUNTS (Keep for testing)
        // ===================================
        $developer = $this->createSeedUser([
            'user_number' => 'USR000001',
            'email' => 'developer@example.com',
            'password' => Hash::make('password'),
            'role' => 'developer',
            'branch_id' => $mainBranch->id,
        ], [
            'first_name' => 'Leo',
            'last_name' => 'Magdarag',
            'title' => 'Member',
            'birth_date' => '2000-01-01',
            'barangay' => 'Santo Tomas',
            'street' => 'Purok 1',
            'phone' => '09171234590',
            'employment' => [
                'employment_type' => 'private',
                'company' => null,
                'position' => 'Systems Developer',
                'monthly_income' => 65000,
            ],
            'identifiers' => $this->governmentIdentifiers('USR000001', 'Santo Tomas'),
        ]);
        $developer->assignRole('developer');

        // Seeded officers use Main as their home branch. Main-branch officers
        // can select the branch they are working with, while production staff
        // assigned to another branch remain scoped there.
        $officers = [
            ['role' => 'admin', 'userNumber' => 'USR000002', 'firstName' => 'Quennie Joy', 'lastName' => 'Almazan', 'title' => 'Member', 'birthDate' => '1985-01-20', 'gender' => 'female', 'barangay' => 'Santo Tomas', 'position' => 'System Administrator', 'type' => 'self-employed', 'company' => null, 'income' => 72000, 'branch' => $mainBranch->id, 'monthsAgo' => 132],
            ['role' => 'manager', 'userNumber' => 'USR000003', 'firstName' => 'Carl Michael', 'lastName' => 'Bigal', 'title' => 'Member', 'birthDate' => '1983-09-14', 'gender' => 'male', 'barangay' => 'San Isidro', 'position' => 'General Manager', 'type' => 'private', 'company' => 'Settlers Credit Cooperative', 'income' => 58000, 'branch' => $mainBranch->id, 'monthsAgo' => 128],
            ['role' => 'accountant', 'userNumber' => 'USR000004', 'firstName' => 'Jasmin', 'lastName' => 'Latayan', 'title' => 'Accountant', 'birthDate' => '1990-05-06', 'gender' => 'female', 'barangay' => 'Mabacan', 'position' => 'Staff Accountant', 'type' => 'private', 'company' => 'Settlers Credit Cooperative', 'income' => 41000, 'branch' => $mainBranch->id, 'monthsAgo' => 74],
            ['role' => 'loan', 'userNumber' => 'USR000005', 'firstName' => 'Leanard', 'lastName' => 'Guiriba', 'title' => 'Loan Officer', 'birthDate' => '1988-11-23', 'gender' => 'male', 'barangay' => 'Masiit', 'position' => 'Loan Officer', 'type' => 'private', 'company' => 'Settlers Credit Cooperative', 'income' => 45000, 'branch' => $mainBranch->id, 'monthsAgo' => 86],
        ];

        foreach ($officers as $officer) {
            $userNumber = $officer['userNumber'];

            $created = $this->createSeedUser([
                'user_number' => $userNumber,
                'email' => 'leo.baluyot039+'.strtolower($officer['role']).'@gmail.com',
                'password' => Hash::make('password'),
                'role' => $officer['role'],
                'is_officer' => true,
                'branch_id' => $officer['branch'],
                'created_at' => Carbon::now()->subMonths($officer['monthsAgo'])->startOfMonth()->day(5)->setTime(9, 0),
            ], [
                'first_name' => $officer['firstName'],
                'last_name' => $officer['lastName'],
                'title' => $officer['title'],
                'birth_date' => $officer['birthDate'],
                'birthplace' => 'Calauan, Laguna',
                'gender' => $officer['gender'],
                'education' => 'Bachelor\'s Degree',
                'civil_status' => 'married',
                'religion' => 'Catholic',
                'phone' => '0917'.$this->digitsFrom($userNumber.'|phone', 7),
                'barangay' => $officer['barangay'],
                'street' => 'Purok 1',
                'employment' => [
                    'employment_type' => $officer['type'],
                    'company' => $officer['company'],
                    'position' => $officer['position'],
                    'monthly_income' => $officer['income'],
                ],
                'identifiers' => $this->governmentIdentifiers($userNumber, $officer['barangay']),
            ]);

            $created->assignRole($officer['role']);
        }

        // ===================================
        // ROSTERED MEMBERS
        // ===================================
        foreach (MemberRoster::all() as $member) {
            $branch = $branches[$member['branch_code']] ?? $mainBranch;
            $joinedAt = MemberRoster::joinedAt($member);

            $created = $this->createSeedUser([
                'user_number' => $member['user_number'],
                'email' => $member['email'] ?? $this->buildMemberEmail($member['first_name'], $member['last_name']),
                'password' => Hash::make('password'),
                'role' => 'member',
                'branch_id' => $branch->id,
                'special_loan_eligible' => (bool) $member['special_loan_eligible'],
                'profile_completed_at' => $joinedAt->copy()->addDays(21)->setTime(14, 30),
                'created_at' => $joinedAt,
            ], [
                'first_name' => $member['first_name'],
                'mid_name' => $member['mid_name'] ?? null,
                'last_name' => $member['last_name'],
                'title' => 'Member',
                'birth_date' => $member['birth_date'],
                'birthplace' => 'Calauan, Laguna',
                'gender' => $member['gender'],
                'education' => $member['education'],
                'civil_status' => $member['civil_status'],
                'religion' => $member['religion'],
                'phone' => $member['phone'],
                'barangay' => $member['barangay'],
                'street' => $member['street'],
                'employment' => [
                    'employment_type' => $member['employment']['type'],
                    'company' => $member['employment']['company'],
                    'position' => $member['employment']['position'],
                    'monthly_income' => $member['employment']['monthly_income'],
                ],
                'identifiers' => $this->governmentIdentifiers($member['user_number'], $member['barangay']),
            ]);

            $created->assignRole('member');
        }
    }
}

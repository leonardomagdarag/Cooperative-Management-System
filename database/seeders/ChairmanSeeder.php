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
 * The cooperative's chairman officer.
 *
 * The built-in officer roster ships admin, manager, accountant and loan. The
 * chairman seat is the fifth officer slot, and the only one whose role carries
 * `oversight.approve`, so it is filled here rather than in UserSeeder: feature
 * tests seed UserSeeder directly and create their own throwaway chairman with
 * QaChairmanSeeder when they need one, and a second chairman in those tests
 * would change the shape of the officer list.
 *
 * The identity matches QaChairmanSeeder's so the QA fixtures and the full seed
 * describe the same person, but this account takes USR000006 — the empty
 * officer slot — and is called only from DatabaseSeeder.
 */
class ChairmanSeeder extends Seeder
{
    private const EMAIL = 'leo.baluyot039+chairman@gmail.com';

    private const USER_NUMBER = 'USR000006';

    public function run(): void
    {
        $email = User::normalizeEmail(self::EMAIL);
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = $this->createChairman($email);
        }

        // The countersign workflow needs the chairman role and the
        // `oversight.approve` permission that ships with it, so re-assert them
        // on every run. The second pass of SpatieRolePermissionSeeder re-derives
        // officer roles too, so this stays stable when the seed is re-run.
        $user->forceFill([
            'role' => 'chairman',
            'is_officer' => true,
            'account_status' => 'active',
            'profile_completed_at' => $user->profile_completed_at ?? now(),
        ])->save();

        $user->syncRoles(['chairman']);

        $this->command?->info('Chairman officer ready: '.$user->user_number.' ('.$user->email.').');
    }

    private function createChairman(string $email): User
    {
        // UserSeeder is the normal source of both, but this seeder should also
        // stand on its own if it is ever called in isolation.
        if (! Storage::disk('public')->exists('placeholders/dummy_id.svg')) {
            Storage::disk('public')->put('placeholders/dummy_id.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150"><rect width="200" height="150" fill="#ddd"/><text x="100" y="75" font-family="sans-serif" font-size="20" text-anchor="middle" fill="#555">ID Document</text></svg>');
        }
        if (! Storage::disk('public')->exists('placeholders/dummy_profile.svg')) {
            Storage::disk('public')->put('placeholders/dummy_profile.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="#ddd"/><circle cx="100" cy="80" r="40" fill="#999"/><path d="M40 180 q 60 -80 120 0" stroke="#999" stroke-width="10" fill="none"/></svg>');
        }

        $branch = Branch::query()->where('is_head_office', true)->first()
            ?? Branch::query()->where('code', 'BR000001')->first();

        $barangay = 'Santo Tomas';

        $address = Address::create(array_merge(MemberRoster::MUNICIPALITY, [
            'barangay' => $barangay,
            'street' => 'Purok 2, Sitio Bayan',
        ]));

        $contact = Contact::create([
            'phone_no' => '09182273415',
            // Leave the phone unverified: UserSeeder verifies exactly one
            // contact (the real-gmail member), which the end-to-end test asserts.
            'phone_verified_at' => null,
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $employment = Employment::create([
            'employment_type' => 'self-employed',
            'company' => null,
            'position' => 'Board Chairman',
            'monthly_income' => 85000,
        ]);

        $memberId = MemberId::create([
            'national_id' => '5123-8841-2290-0117',
            'national_id_photo' => 'placeholders/dummy_id.svg',
            'tin' => '294-881-204-000',
            'tin_photo' => 'placeholders/dummy_id.svg',
            'sss' => '34-2017745-8',
            'sss_photo' => 'placeholders/dummy_id.svg',
            'philhealth' => '12-345678901-2',
            'philhealth_photo' => 'placeholders/dummy_id.svg',
            'barangay_id' => MemberRoster::barangayCode($barangay) ?? '',
            'barangay_id_photo' => 'placeholders/dummy_id.svg',
            'proof_of_registration_path' => 'placeholders/dummy_id.svg',
            'other_document_path' => 'placeholders/dummy_id.svg',
        ]);

        $personalData = PersonalData::create([
            'title' => 'Chairman',
            'first_name' => 'Eduardo',
            'mid_name' => 'M.',
            'last_name' => 'Reyes',
            'birth_date' => '1975-04-15',
            'birthplace' => 'Calauan, Laguna',
            'gender' => 'male',
            'education' => "Master's Degree",
            'civil_status' => 'married',
            'religion' => 'Catholic',
            'profile_picture' => 'placeholders/dummy_profile.svg',
            'address_id' => $address->id,
            'contact_id' => $contact->id,
            'employment_id' => $employment->id,
            'member_id' => $memberId->id,
        ]);

        return User::withoutTimestamps(function () use ($email, $branch, $personalData) {
            $user = new User;
            $user->forceFill([
                'user_number' => self::USER_NUMBER,
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => 'chairman',
                'is_officer' => true,
                'account_status' => 'active',
                'branch_id' => $branch?->id,
                'personal_data_id' => $personalData->id,
                'profile_completed_at' => now(),
            ]);
            $user->email_verified_at = now();
            $user->created_at = Carbon::now()->subMonths(150)->startOfMonth()->day(5)->setTime(9, 0);
            $user->updated_at = $user->created_at;
            $user->save();

            return $user;
        });
    }
}

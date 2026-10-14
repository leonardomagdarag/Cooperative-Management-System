<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\Employment;
use App\Models\MemberId;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class QaChairmanSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Branch::query()->where('is_head_office', true)->first()
            ?? Branch::query()->first();

        $email = User::normalizeEmail('leo.baluyot039+chairman@gmail.com');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $address = Address::create([
                'country' => 'Philippines',
                'region' => 'REGION IV-A (CALABARZON)',
                'province' => 'Laguna',
                'city_municipality' => 'Calauan',
                'barangay' => 'Santo Tomas',
                'street' => 'Purok 1',
                'zip_code' => '4012',
            ]);

            $contact = Contact::create([
                'phone_no' => '09179876543',
                'phone_verified_at' => now(),
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
                'national_id' => '1234-5678-9012-3456',
                'national_id_photo' => 'placeholders/dummy_id.svg',
                'tin' => '123-456-789-000',
                'tin_photo' => 'placeholders/dummy_id.svg',
            ]);

            $personalData = PersonalData::create([
                'title' => 'Chairman',
                'first_name' => 'Eduardo',
                'mid_name' => 'M.',
                'last_name' => 'Reyes',
                'birth_date' => '1975-04-15',
                'birthplace' => 'Calauan, Laguna',
                'gender' => 'male',
                'education' => 'Master\'s Degree',
                'civil_status' => 'married',
                'religion' => 'Catholic',
                'profile_picture' => 'placeholders/dummy_profile.svg',
                'address_id' => $address->id,
                'contact_id' => $contact->id,
                'employment_id' => $employment->id,
                'member_id' => $memberId->id,
            ]);

            $user = new User;
            $user->user_number = 'USR000099';
            $user->email = $email;
            $user->password = Hash::make('password');
            $user->role = 'chairman';
            $user->is_officer = true;
            $user->account_status = 'active';
            $user->branch_id = $mainBranch?->id;
            $user->personal_data_id = $personalData->id;
            $user->email_verified_at = now();
            $user->profile_completed_at = now();
            $user->save();
        } else {
            $user->forceFill([
                'role' => 'chairman',
                'is_officer' => true,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'profile_completed_at' => now(),
            ])->save();
        }

        $user->syncRoles(['chairman']);
        $user->givePermissionTo('oversight.approve');
    }
}

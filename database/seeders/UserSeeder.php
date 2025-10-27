<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create developer account
        $contact = \App\Models\Contact::create([
            'phone_no' => '09170000000',
            'email' => 'developer@example.com',
        ]);
        $personalData = \App\Models\PersonalData::create([
            'first_name' => 'Leo',
            'last_name' => 'Magdarag',
            'birth_date' => '2000-01-01',
            'contact_id' => $contact->contact_id,
        ]);
        \App\Models\User::create([
            'personal_data_id' => $personalData->personal_data_id,
            'email' => $contact->email,
            'password' => Hash::make('12345Dev'),
            'role' => 'developer',
        ]);

        // Create an admin user
        $contact = \App\Models\Contact::create([
            'phone_no' => '09171234567',
            'email' => 'admin@example.com',
        ]);

        $personalData = \App\Models\PersonalData::create([
            'first_name' => 'Admin',
            'last_name' => 'Account',
            'birth_date' => '2000-01-01',
            'contact_id' => $contact->contact_id,
        ]);

        \App\Models\User::create([
            'personal_data_id' => $personalData->personal_data_id,

            'email' => $contact->email,
            'password' => Hash::make('12345'),
            'role' => 'admin',
        ]);

        // Create an officer user
        $contact = \App\Models\Contact::create([
            'phone_no' => '09179876543',
            'email' => 'officer@example.com',
        ]);
        $personalData = \App\Models\PersonalData::create([
            'first_name' => 'Officer',
            'last_name' => 'Account',
            'birth_date' => '2000-01-01',
            'contact_id' => $contact->contact_id,
        ]);
        \App\Models\User::create([
            'personal_data_id' => $personalData->personal_data_id,
            'email' => $contact->email,
            'password' => Hash::make('12345'),
            'role' => 'officer',
        ]);

        // Create an ordinary user
        $contact = \App\Models\Contact::create([
            'phone_no' => '09173456789',
            'email' => 'member@example.com',
        ]);
        $personalData = \App\Models\PersonalData::create([
            'first_name' => 'Member',
            'last_name' => 'Account',
            'birth_date' => '2000-01-01',
            'contact_id' => $contact->contact_id,
        ]);

        \App\Models\User::create([
            'personal_data_id' => $personalData->personal_data_id,
            'email' => $contact->email,
            'password' => Hash::make('12345'),
            'role' => 'member',
        ]);

        // Create an invalid user
        $contact = \App\Models\Contact::create([
            'phone_no' => '09170001111',
            'email' => 'invalid@example.com'
        ]);
        $personalData = \App\Models\PersonalData::create([
            'first_name' => 'Invalid',
            'last_name' => 'Account',
            'birth_date' => '2000-01-01',
            'contact_id' => $contact->contact_id,
        ]);
        \App\Models\User::create([
            'personal_data_id' => $personalData->personal_data_id,
            'email' => $contact->email,
            'password' => Hash::make('12345'),
            'role' => 'inactive',
        ]);

        // Create additional member users
        // Member 2: Cris David Jarabejo
        $contact = \App\Models\Contact::create([
            'phone_no' => '09181234567',
            'email' => 'cris.jarabejo@example.com',
        ]);
        $personalData = \App\Models\PersonalData::create([
            'first_name' => 'Cris David',
            'last_name' => 'Jarabejo',
            'birth_date' => '1970-05-15',
            'contact_id' => $contact->contact_id,
        ]);
        \App\Models\User::create([
            'personal_data_id' => $personalData->personal_data_id,
            'email' => $contact->email,
            'password' => Hash::make('12345'),
            'role' => 'member',
        ]);

        // Member 3: Alyson Penano
        $contact = \App\Models\Contact::create([
            'phone_no' => '09192345678',
            'email' => 'alyson.penano@example.com',
        ]);
        $personalData = \App\Models\PersonalData::create([
            'first_name' => 'Alyson',
            'last_name' => 'Penano',
            'birth_date' => '1985-08-22',
            'contact_id' => $contact->contact_id,
        ]);
        \App\Models\User::create([
            'personal_data_id' => $personalData->personal_data_id,
            'email' => $contact->email,
            'password' => Hash::make('12345'),
            'role' => 'member',
        ]);

        // Member 4: Glendo Camarines
        $contact = \App\Models\Contact::create([
            'phone_no' => '09203456789',
            'email' => 'glendo.camarines@example.com',
        ]);
        $personalData = \App\Models\PersonalData::create([
            'first_name' => 'Glendo',
            'last_name' => 'Camarines',
            'birth_date' => '1992-11-30',
            'contact_id' => $contact->contact_id,
        ]);
        \App\Models\User::create([
            'personal_data_id' => $personalData->personal_data_id,
            'email' => $contact->email,
            'password' => Hash::make('12345'),
            'role' => 'member',
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\PersonalData;
use App\Models\Address;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonalDataFactory extends Factory
{
    protected $model = PersonalData::class;

    public function definition(): array
    {
        $suffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', null, null, null];
        $titles = ['Chairman', 'Vice Chairman', 'Secretary', 'Treasurer', 'Loan Officer', 'Member'];
        $genders = ['male', 'female'];
        $civilStatuses = ['single', 'married', 'widowed', 'separated'];
        $educationLevels = ['Elementary', 'High School', 'Vocational', 'Bachelor\'s Degree', 'Master\'s Degree', 'Doctorate'];
        $religions = ['Catholic', 'Christian', 'Islam', 'Buddhism', 'Other'];

        return [
            'title' => fake()->randomElement($titles),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'mid_name' => fake()->optional(0.7)->lastName(),
            'suffix' => fake()->randomElement($suffixes),
            'birth_date' => fake()->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d'),
            'birthplace' => fake()->city(),
            'gender' => fake()->randomElement($genders),
            'education' => fake()->randomElement($educationLevels),
            'civil_status' => fake()->randomElement($civilStatuses),
            'religion' => fake()->randomElement($religions),
            'address_id' => Address::factory(),
            'contact_id' => Contact::factory(),
            'employment_id' => \App\Models\Employment::factory(),
            'member_id' => \App\Models\MemberId::factory(),
        ];
    }
}

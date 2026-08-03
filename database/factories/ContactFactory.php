<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $prefixes = ['0915', '0916', '0917', '0918', '0919', '0920', '0921', '0922', '0923', '0925', '0926', '0927', '0928', '0929', '0930', '0935', '0936', '0945', '0947', '0955', '0956', '0965', '0967', '0975', '0976', '0977', '0978', '0979', '0981', '0989', '0992', '0993', '0994', '0995', '0996', '0997', '0998', '0999'];

        return [
            'phone_no' => fake()->randomElement($prefixes).fake()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}

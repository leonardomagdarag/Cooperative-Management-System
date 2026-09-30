<?php

namespace Database\Factories;

use App\Models\Address;
use Database\Seeders\Support\MemberRoster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Addresses of members of this cooperative.
 *
 * The membership is drawn from Calauan, Laguna, so a factory address anywhere
 * else in the country would contradict the branch the member is filed under.
 * The municipality, province, region and zip are therefore fixed and only the
 * barangay and street vary.
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        $municipality = MemberRoster::MUNICIPALITY;

        $barangay = fake()->randomElement(MemberRoster::CALAUAN_BARANGAYS);

        return [
            'country' => $municipality['country'],
            'region' => $municipality['region'],
            'province' => $municipality['province'],
            'city_municipality' => $municipality['city_municipality'],
            'barangay' => $barangay,
            'street' => sprintf(
                'Purok %d, sitio %s',
                fake()->numberBetween(1, 7),
                fake()->randomElement(['Maligaya', 'Bayanihan', 'Sto. Niño', 'Ligtas', 'Kaunlaran', 'Mabuhay']),
            ),
            'zip_code' => $municipality['zip_code'],
        ];
    }
}

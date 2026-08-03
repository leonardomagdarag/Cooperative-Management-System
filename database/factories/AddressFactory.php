<?php

namespace Database\Factories;

use App\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        $cities = [
            'Quezon City',
            'Manila',
            'Caloocan',
            'Davao City',
            'Cebu City',
            'Makati',
            'Pasig',
            'Taguig',
            'Parañaque',
            'Las Piñas',
            'Mandaluyong',
            'Muntinlupa',
            'San Juan',
            'Marikina',
            'Valenzuela',
        ];

        $regions = [
            'NCR' => 'Metro Manila',
            'Region VII' => 'Central Visayas',
            'Region XI' => 'Davao Region',
            'Region III' => 'Central Luzon',
            'Region IV-A' => 'CALABARZON',
        ];

        $barangays = [
            'Barangay 1',
            'Barangay 2',
            'San Antonio',
            'Santa Cruz',
            'Poblacion',
            'New Era',
            'Commonwealth',
            'Tandang Sora',
            'Holy Spirit',
            'Bagong Silang',
            'Veterans Village',
        ];

        $region = fake()->randomElement(array_keys($regions));

        return [
            'country' => 'Philippines',
            'region' => $regions[$region],
            'province' => fake()->randomElement(['Metro Manila', 'Cebu', 'Davao del Sur', 'Bulacan', 'Cavite']),
            'city_municipality' => fake()->randomElement($cities),
            'barangay' => fake()->randomElement($barangays),
            'street' => fake()->streetAddress(),
            'zip_code' => fake()->randomElement(['1100', '1200', '1400', '1500', '1600', '6000', '8000']),
        ];
    }
}

<?php

use Database\Seeders\PSGCSeeder;
use Illuminate\Support\Facades\DB;

test('psgc regions endpoint returns the seeded geographic data', function () {
    $this->seed(PSGCSeeder::class);

    $this->getJson('/psgc/regions?limit=100&order_by=name')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['code', 'name', 'short_name']],
            'recordsTotal',
            'recordsFiltered',
        ])->assertJsonCount(18, 'data');
});

test('psgc cascaded selectors return data for each level', function () {
    $this->seed(PSGCSeeder::class);

    $regionCode = DB::table('regions')->value('code');

    $this->getJson('/psgc/provinces?region_code='.$regionCode.'&limit=100&order_by=name')
        ->assertOk()
        ->assertJsonPath('recordsTotal', fn ($total) => $total > 0)
        ->assertJsonStructure(['data' => [['code', 'name', 'region_code']]]);

    $provinceCode = DB::table('provinces')->where('region_code', $regionCode)->value('code');

    $this->getJson('/psgc/cities?province_code='.$provinceCode.'&limit=100&order_by=name')
        ->assertOk()
        ->assertJsonPath('recordsTotal', fn ($total) => $total > 0)
        ->assertJsonStructure(['data' => [['code', 'name', 'province_code']]]);

    $cityCode = DB::table('cities')->where('province_code', $provinceCode)->value('code');

    $this->getJson('/psgc/barangays?city_code='.$cityCode.'&limit=100&order_by=name')
        ->assertOk()
        ->assertJsonPath('recordsTotal', fn ($total) => $total > 0)
        ->assertJsonStructure(['data' => [['code', 'name', 'city_code']]]);
});

test('database seeder includes psgc reference data', function () {
    // Simulate a fresh `migrate --seed` so registration address selectors work.
    $this->seed();

    expect(DB::table('regions')->count())->toBeGreaterThan(0);
    expect(DB::table('barangays')->count())->toBeGreaterThan(0);
});

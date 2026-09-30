<?php

namespace Database\Factories;

use App\Models\MemberId;
use Database\Seeders\Support\MemberRoster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Government identifiers of a member.
 *
 * Each number is generated in the format the issuing agency actually uses, and
 * the barangay reference is a real PSGC code of Calauan rather than a made-up
 * string, so it resolves against the barangays table. The Philippine national ID
 * is optional because the government never issued one: members who do not have
 * it simply leave the field blank.
 */
class MemberIdFactory extends Factory
{
    protected $model = MemberId::class;

    public function definition(): array
    {
        $barangay = fake()->randomElement(MemberRoster::CALAUAN_BARANGAYS);

        return [
            'tin' => fake()->numerify('###-###-###'),
            'sss' => fake()->numerify('##-#######-#'),
            'philhealth' => fake()->numerify('##-#########-#'),
            'barangay_id' => MemberRoster::barangayCode($barangay),
        ];
    }

    /**
     * A member who carries the older paper national ID issued to residents
     * before the registry was digitised.
     */
    public function withLegacyNationalId(): static
    {
        return $this->state(fn () => [
            'national_id' => fake()->numerify('####-####-####-####'),
        ]);
    }
}

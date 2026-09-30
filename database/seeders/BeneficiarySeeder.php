<?php

namespace Database\Seeders;

use App\Models\Beneficiary;
use App\Models\User;
use Database\Seeders\Support\MemberRoster;
use Illuminate\Database\Seeder;

/**
 * Who each member would leave their savings to.
 *
 * Beneficiaries are family, so they take the member's own surname and are given
 * an age that fits the relationship declared. Names are fixed per member rather
 * than drawn at random, so a re-seed does not hand the same member a different
 * family, and the list reads as a set of real households in Calauan.
 */
class BeneficiarySeeder extends Seeder
{
    /**
     * The household recorded for each member, by user number.
     *
     * Every member is married or single with living parents, so the roster pairs
     * each person with a spouse where the civil status says so and a parent
     * otherwise. A member who holds a loan names an extra beneficiary, since a
     * co-signed borrower is expected to.
     *
     * @var array<string, list<array{first: string, last: string, relationship: string, born: string}>>
     */
    private const HOUSEHOLDS = [
        'USR000007' => [
            ['first' => 'Marissa', 'last' => 'Ramos', 'relationship' => 'Spouse', 'born' => '1980-11-02'],
            ['first' => 'Danilo', 'last' => 'Ramos', 'relationship' => 'Father', 'born' => '1952-04-18'],
        ],
        'USR000008' => [
            ['first' => 'Perlita', 'last' => 'Jarabejo', 'relationship' => 'Spouse', 'born' => '1983-08-25'],
            ['first' => 'Alyssa', 'last' => 'Jarabejo', 'relationship' => 'Daughter', 'born' => '2012-06-14'],
            ['first' => 'Marcelo', 'last' => 'Jarabejo', 'relationship' => 'Father', 'born' => '1955-01-30'],
        ],
        'USR000009' => [
            ['first' => 'Teodoro', 'last' => 'Camarines', 'relationship' => 'Father', 'born' => '1951-09-11'],
        ],
        'USR000010' => [
            ['first' => 'Rowena', 'last' => 'Amper', 'relationship' => 'Spouse', 'born' => '1984-12-08'],
            ['first' => 'Christian', 'last' => 'Amper', 'relationship' => 'Son', 'born' => '2014-03-27'],
        ],
        'USR000011' => [
            ['first' => 'Efren', 'last' => 'Velasco', 'relationship' => 'Father', 'born' => '1950-07-19'],
            ['first' => 'Grace', 'last' => 'Velasco', 'relationship' => 'Mother', 'born' => '1953-02-06'],
        ],
        'USR000012' => [
            ['first' => 'Nerissa', 'last' => 'Cabillon', 'relationship' => 'Spouse', 'born' => '1985-05-22'],
            ['first' => 'Josefina', 'last' => 'Cabillon', 'relationship' => 'Mother', 'born' => '1954-10-13'],
        ],
        'USR000013' => [
            ['first' => 'Amalia', 'last' => 'Centeno', 'relationship' => 'Mother', 'born' => '1956-06-09'],
        ],
        'USR000014' => [
            ['first' => 'Benigno', 'last' => 'Penano', 'relationship' => 'Father', 'born' => '1949-03-04'],
            ['first' => 'Corazon', 'last' => 'Penano', 'relationship' => 'Mother', 'born' => '1952-11-25'],
        ],
        'USR000015' => [
            ['first' => 'Lorna', 'last' => 'Premor', 'relationship' => 'Mother', 'born' => '1957-01-17'],
        ],
        'USR000016' => [
            ['first' => 'Cristina', 'last' => 'Magdarag', 'relationship' => 'Spouse', 'born' => '1986-09-08'],
            ['first' => 'Angela', 'last' => 'Magdarag', 'relationship' => 'Daughter', 'born' => '2013-05-21'],
        ],
    ];

    public function run(): void
    {
        $written = 0;

        foreach (MemberRoster::all() as $member) {
            $user = User::query()->where('user_number', $member['user_number'])->first();

            if (! $user) {
                continue;
            }

            foreach (self::HOUSEHOLDS[$member['user_number']] ?? [] as $index => $person) {
                $exists = Beneficiary::query()
                    ->where('user_id', $user->id)
                    ->where('first_name', $person['first'])
                    ->where('last_name', $person['last'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $phone = $index === 0
                    ? $this->contactNumber($member['user_number'], $user)
                    : $this->relativeNumber($member['user_number'], $index);

                Beneficiary::create([
                    'user_id' => $user->id,
                    'first_name' => $person['first'],
                    'last_name' => $person['last'],
                    'mid_name' => $person['first'] === $member['first_name'] ? null : $member['first_name'],
                    'birth_date' => $person['born'],
                    'relationship' => $person['relationship'],
                    'phone_no' => $phone,
                    // The household is in the same barangay as the member, so a
                    // beneficiary is reachable at the same address.
                    'street' => $member['street'],
                    'barangay' => $member['barangay'],
                    'city' => MemberRoster::MUNICIPALITY['city_municipality'],
                    'province' => MemberRoster::MUNICIPALITY['province'],
                    'postal_code' => MemberRoster::MUNICIPALITY['zip_code'],
                ]);

                $written++;
            }
        }

        $this->command?->info('Recorded '.$written.' beneficiaries.');
    }

    /**
     * The member's own number, so a single household is easy to reach.
     */
    private function contactNumber(string $userNumber, User $user): string
    {
        return (string) ($user->contact?->phone_no ?? $user->personalData?->contact?->phone_no ?? '');
    }

    /**
     * A relative's own mobile number.
     *
     * The column holds eleven characters, so relatives are given a mobile like
     * every other contact in the system. The number is derived from the member
     * and the relative's position, so it is stable and distinct from the
     * member's own line.
     */
    private function relativeNumber(string $userNumber, int $index): string
    {
        $prefixes = ['0918', '0921', '0936', '0946', '0956'];
        $prefix = $prefixes[abs(crc32($userNumber.'|'.$index)) % count($prefixes)];

        return $prefix.str_pad((string) (abs(crc32($userNumber.'|'.$index)) % 10000000), 7, '0', STR_PAD_LEFT);
    }
}

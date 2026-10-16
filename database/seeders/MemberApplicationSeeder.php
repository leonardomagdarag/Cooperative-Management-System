<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\MemberApplication;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The membership desk's application tray.
 *
 * A cooperative keeps every application it ever received: two are still waiting
 * for a decision from people who never became members, one records how an
 * existing member joined, and one was turned away for incomplete requirements.
 * Applications from non-members carry no `user_id`; the approved one is linked
 * to the member it produced.
 *
 * Rows are looked up by `original_email`, which is stable across re-seeds, so
 * the tray does not collect duplicates.
 */
class MemberApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Branch::query()->where('code', 'BR000001')->first();
        $satellite = Branch::query()->where('code', 'BR000002')->first();
        $reviewer = User::query()->where('email', 'leo.baluyot039+manager@gmail.com')->first();

        $applications = [
            [
                'original_email' => 'maricel.ibanez@example.com',
                'first_name' => 'Maricel',
                'last_name' => 'Ibanez',
                'phone' => '09183345671',
                'membership_type' => 'Regular',
                'status' => 'pending',
                'branch_id' => $mainBranch?->id,
            ],
            [
                'original_email' => 'rodel.santos@example.com',
                'first_name' => 'Rodel',
                'last_name' => 'Santos',
                'phone' => '09274678312',
                'membership_type' => 'Associate',
                'status' => 'pending',
                'branch_id' => $satellite?->id,
            ],
            [
                'original_email' => 'jomar.aquino@example.com',
                'first_name' => 'Jomar',
                'last_name' => 'Aquino',
                'phone' => '09361209845',
                'membership_type' => 'Regular',
                'status' => 'declined',
                'decline_reason' => 'Incomplete requirements: a valid government ID and proof of income were not submitted.',
                'branch_id' => $mainBranch?->id,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now()->subMonths(3)->setTime(14, 30),
            ],
        ];

        // The newest member's own application, kept as the record of how they joined.
        $member = User::query()->where('user_number', 'USR000015')->first();
        if ($member) {
            $applications[] = [
                'original_email' => $member->email,
                'first_name' => $member->personalData?->first_name ?? 'Member',
                'last_name' => $member->personalData?->last_name ?? '',
                'phone' => $member->personalData?->contact?->phone_no,
                'membership_type' => 'Regular',
                'status' => 'approved',
                'user_id' => $member->id,
                'branch_id' => $member->branch_id,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => $member->created_at?->copy()->addDay()->setTime(10, 15) ?? now(),
            ];
        }

        $created = 0;

        foreach ($applications as $application) {
            $exists = MemberApplication::query()
                ->where('original_email', $application['original_email'])
                ->exists();

            if ($exists) {
                continue;
            }

            MemberApplication::create($application);
            $created++;
        }

        $this->command?->info('Recorded '.$created.' member applications.');
    }
}

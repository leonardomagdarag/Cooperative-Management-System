<?php

namespace Database\Seeders;

use App\Models\PrivilegedActionRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The chairman's oversight tray.
 *
 * Two requests are still waiting for a countersignature and one was turned down
 * last quarter. They are written straight to the table rather than through
 * CountersignService, because going through the service would fire approval
 * notices at the chairman every time the seed runs.
 *
 * The fixed `idempotency_key` values make each row stable across re-seeds; a
 * real request would use a random UUID, but a fixed one lets the seeder find
 * its own rows again.
 */
class OversightRequestSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('user_number', 'USR000002')->first();
        $manager = User::query()->where('user_number', 'USR000003')->first();
        $chairman = User::query()->where('user_number', 'USR000006')->first();

        $requests = [
            [
                'idempotency_key' => '11111111-1111-4111-8111-111111111111',
                'action_type' => 'financial.settings.update',
                'status' => 'pending',
                'payload' => [
                    'settings' => [
                        'financial_savings_interest_rate' => 3.0,
                        'financial_savings_interest_interval' => 'monthly',
                    ],
                    'notify_affected' => true,
                    'services' => ['deposits'],
                ],
                'requested_by' => $admin?->id,
                'requested_at' => now()->subHours(3),
                'expires_at' => now()->addHours(21),
            ],
            [
                'idempotency_key' => '22222222-2222-4222-8222-222222222222',
                'action_type' => 'permissions.update',
                'status' => 'pending',
                'payload' => [
                    'role' => 'loan',
                    'permissions' => ['dashboard.view', 'loans.view', 'loans.create', 'loans.update'],
                ],
                'requested_by' => $manager?->id,
                'requested_at' => now()->subHours(9),
                'expires_at' => now()->addHours(15),
            ],
            [
                'idempotency_key' => '33333333-3333-4333-8333-333333333333',
                'action_type' => 'settings.update',
                'status' => 'rejected',
                'payload' => [
                    'settings' => ['service_login_member_enabled' => false],
                    'sync_login' => true,
                ],
                'requested_by' => $manager?->id,
                'requested_at' => now()->subMonths(2)->setTime(9, 20),
                'expires_at' => now()->subMonths(2)->setTime(9, 20)->addHours(24),
                'decided_by' => $chairman?->id,
                'decided_at' => now()->subMonths(2)->setTime(11, 5),
                'decision_note' => 'Members still need to sign in to file loan requests. Decline for now.',
            ],
        ];

        $written = 0;

        foreach ($requests as $request) {
            $existing = PrivilegedActionRequest::query()
                ->where('idempotency_key', $request['idempotency_key'])
                ->exists();

            if ($existing) {
                continue;
            }

            PrivilegedActionRequest::create($request);
            $written++;
        }

        $this->command?->info('Recorded '.$written.' oversight requests.');
    }
}

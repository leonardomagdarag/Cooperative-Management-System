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
            [
                'idempotency_key' => '44444444-4444-4444-8444-444444444444',
                'action_type' => 'financial.settings.update',
                'status' => 'executed',
                'payload' => [
                    'settings' => [
                        'financial_savings_interest_rate' => 2.5,
                        'financial_share_capital_interest_rate' => 4.0,
                        'financial_savings_interest_interval' => 'monthly',
                        'financial_share_capital_interest_interval' => 'monthly',
                    ],
                    'notify_affected' => false,
                ],
                'requested_by' => $admin?->id,
                'requested_at' => now()->subMonths(6)->startOfMonth()->day(2)->setTime(10, 0),
                'expires_at' => now()->subMonths(6)->startOfMonth()->day(3)->setTime(10, 0),
                'decided_by' => $chairman?->id,
                'decided_at' => now()->subMonths(6)->startOfMonth()->day(2)->setTime(14, 15),
                'otp_verified_at' => now()->subMonths(6)->startOfMonth()->day(2)->setTime(14, 15),
                'started_at' => now()->subMonths(6)->startOfMonth()->day(2)->setTime(14, 15),
                'executed_at' => now()->subMonths(6)->startOfMonth()->day(2)->setTime(14, 15),
                'decision_note' => 'Approved per Board Resolution No. 2026-04 setting standing savings and share-capital rates.',
                'execution_result' => [
                    'status' => 'success',
                    'updated_keys' => [
                        'financial_savings_interest_rate',
                        'financial_share_capital_interest_rate',
                        'financial_savings_interest_interval',
                        'financial_share_capital_interest_interval',
                    ],
                ],
            ],
            [
                'idempotency_key' => '55555555-5555-4555-8555-555555555555',
                'action_type' => 'backup.daily.schedule.update',
                'status' => 'executed',
                'payload' => [
                    'time' => '03:00',
                ],
                'requested_by' => $admin?->id,
                'requested_at' => now()->subMonths(4)->startOfMonth()->day(10)->setTime(11, 30),
                'expires_at' => now()->subMonths(4)->startOfMonth()->day(11)->setTime(11, 30),
                'decided_by' => $chairman?->id,
                'decided_at' => now()->subMonths(4)->startOfMonth()->day(10)->setTime(13, 45),
                'otp_verified_at' => now()->subMonths(4)->startOfMonth()->day(10)->setTime(13, 45),
                'started_at' => now()->subMonths(4)->startOfMonth()->day(10)->setTime(13, 45),
                'executed_at' => now()->subMonths(4)->startOfMonth()->day(10)->setTime(13, 45),
                'decision_note' => 'Confirmed 03:00 AM daily backup window.',
                'execution_result' => ['status' => 'success', 'time' => '03:00'],
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

            $createdAt = $request['requested_at'] ?? now();
            $updatedAt = $request['executed_at'] ?? $request['decided_at'] ?? $createdAt;

            PrivilegedActionRequest::withoutTimestamps(function () use ($request, $createdAt, $updatedAt) {
                $model = new PrivilegedActionRequest($request);
                $model->created_at = $createdAt;
                $model->updated_at = $updatedAt;
                $model->save();
            });
            $written++;
        }

        $this->command?->info('Recorded '.$written.' oversight requests.');
    }
}

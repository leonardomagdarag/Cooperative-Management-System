<?php

namespace Database\Seeders;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Devices members and officers have marked as trusted, so the one-time code is
 * skipped on a familiar browser.
 *
 * A couple of members and one officer keep a device each. Fingerprints are
 * derived from a stable seed, so re-seeding refreshes the same rows instead of
 * inventing new devices.
 */
class TrustedDeviceSeeder extends Seeder
{
    /**
     * @var list<array{user_number: string, label: string, ip: string, agent: string, trusted_days: int, used_days: int}>
     */
    private const DEVICES = [
        [
            'user_number' => 'USR000007',
            'label' => 'Chrome on Windows',
            'ip' => '112.198.115.20',
            'agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'trusted_days' => 18,
            'used_days' => 1,
        ],
        [
            'user_number' => 'USR000012',
            'label' => 'Safari on iPhone',
            'ip' => '49.145.203.77',
            'agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
            'trusted_days' => 5,
            'used_days' => 0,
        ],
        [
            'user_number' => 'USR000004',
            'label' => 'Edge on Windows',
            'ip' => '203.177.141.9',
            'agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 Edg/124.0',
            'trusted_days' => 27,
            'used_days' => 2,
        ],
    ];

    public function run(): void
    {
        $written = 0;

        foreach (self::DEVICES as $device) {
            $user = User::query()->where('user_number', $device['user_number'])->first();

            if (! $user) {
                continue;
            }

            $fingerprint = hash('sha256', $device['user_number'].'|trusted-device');

            TrustedDevice::query()->updateOrCreate(
                ['user_id' => $user->id, 'fingerprint' => $fingerprint],
                [
                    'device_label' => $device['label'],
                    'last_ip_address' => $device['ip'],
                    'user_agent' => $device['agent'],
                    'trusted_until' => now()->addDays($device['trusted_days']),
                    'last_used_at' => now()->subDays($device['used_days'])->setTime(8, 45),
                    'is_active' => true,
                ],
            );

            $written++;
        }

        $this->command?->info('Recorded '.$written.' trusted devices.');
    }
}

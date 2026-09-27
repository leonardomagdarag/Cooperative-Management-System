<?php

use App\Models\BackupRun;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    // Off-site (Backblaze B2) is unconfigured in tests by default so no live
    // network calls are attempted.
    config([
        'filesystems.disks.b2.key' => null,
        'filesystems.disks.b2.secret' => null,
        'filesystems.disks.b2.bucket' => null,
        'filesystems.disks.b2.endpoint' => null,
    ]);
});

function backupAdminWith(string $permission): User
{
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin-'.Str::random(8).'@example.com',
        'password' => 'password',
        'account_status' => 'active',
    ]);
    $admin->syncRoles(['admin']);
    $admin->givePermissionTo($permission);

    return $admin;
}

test('backups index requires the backups.view permission', function () {
    $member = User::factory()->create([
        'role' => 'member',
        'email' => 'member-'.Str::random(8).'@example.com',
        'password' => 'password',
        'account_status' => 'active',
    ]);

    $this->actingAs($member)
        ->getJson('/api/backups')
        ->assertStatus(403);
});

test('admin with backups.view can read backup index and recent runs', function () {
    $admin = backupAdminWith('backups.view');

    BackupRun::create([
        'tier' => 'daily',
        'scope' => 'both',
        'status' => 'success',
        'filename' => 'Laravel/backup-daily-test.zip',
        'size' => 123,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson('/api/backups')
        ->assertOk()
        ->assertJsonPath('data.settings.encryption_enabled', true)
        ->assertJsonPath('data.settings.offsite.configured', false)
        ->assertJsonStructure([
            'data' => [
                'settings' => ['tiers' => ['hourly', 'daily', 'offsite']],
                'tiers' => ['hourly', 'daily', 'offsite'],
            ],
        ])
        ->assertJsonCount(1, 'data.recent_runs');
});

test('admin can toggle backup encryption from settings', function () {
    $admin = backupAdminWith('backups.manage');

    $this->actingAs($admin)
        ->putJson('/api/backups/settings', ['encryption_enabled' => false])
        ->assertOk()
        ->assertJsonPath('message', 'Backup settings updated.');

    expect(SystemSetting::get('backup_encryption_enabled'))->toBeFalse();
});

test('backup settings rejects an empty payload', function () {
    $admin = backupAdminWith('backups.manage');

    $this->actingAs($admin)
        ->putJson('/api/backups/settings', [])
        ->assertStatus(422);
});

test('enabling the off-site tier while unconfigured is rejected on run', function () {
    $admin = backupAdminWith('backups.manage');

    $this->actingAs($admin)
        ->postJson('/api/backups/offsite/run', ['scope' => 'both'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Backblaze B2 is not configured. Set the AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET and AWS_ENDPOINT values in .env first.']);
});

test('restore rejects when unauthenticated actor lacks backups.restore permission', function () {
    $member = User::factory()->create([
        'role' => 'member',
        'email' => 'member-r-'.Str::random(8).'@example.com',
        'password' => 'password',
        'account_status' => 'active',
    ]);

    $this->actingAs($member)
        ->postJson('/api/backups/restore', [
            'tier' => 'hourly',
            'path' => 'Laravel/backup-hourly-test.zip',
            'scope' => 'both',
            'current_password' => 'password',
            'confirmation' => 'RESTORE',
            'archive_password' => 'test-key',
        ])
        ->assertStatus(403);
});

test('run rejects invalid tier or invalid scope', function () {
    $admin = backupAdminWith('backups.manage');

    $this->actingAs($admin)
        ->postJson('/api/backups/invalid_tier/run')
        ->assertStatus(422)
        ->assertJson(['message' => 'Invalid tier.']);

    $this->actingAs($admin)
        ->postJson('/api/backups/hourly/run', ['scope' => 'invalid_scope'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Invalid scope.']);
});

test('backups.manage queues an hourly files backup', function () {
    config(['backup.backup.password' => 'test-encryption-key']);
    config(['backup.backup.encryption' => 'none']);

    $admin = backupAdminWith('backups.manage');

    $this->actingAs($admin)
        ->postJson('/api/backups/hourly/run', ['scope' => 'files'])
        ->assertStatus(202)
        ->assertJsonPath('run.status', 'queued');

    expect(BackupRun::where('tier', 'hourly')->latest()->first()->status)->toBe('success');
});

test('off-site status and test endpoint require backups.manage and report unconfigured', function () {
    $member = User::factory()->create([
        'role' => 'member',
        'email' => 'member-c-'.Str::random(8).'@example.com',
        'password' => 'password',
        'account_status' => 'active',
    ]);

    $this->actingAs($member)
        ->postJson('/api/backups/offsite/test')
        ->assertStatus(403);

    $admin = backupAdminWith('backups.manage');

    $this->actingAs($admin)
        ->postJson('/api/backups/offsite/test')
        ->assertStatus(422)
        ->assertJsonPath('data.configured', false)
        ->assertJsonPath('data.reachable', null);
});

test('off-site reports as connected when configured and reachable', function () {
    Storage::fake('b2');
    config([
        'filesystems.disks.b2.key' => 'some-key',
        'filesystems.disks.b2.secret' => 'some-secret',
        'filesystems.disks.b2.region' => 'us-east-005',
        'filesystems.disks.b2.bucket' => 'some-bucket',
        'filesystems.disks.b2.endpoint' => 'https://s3.us-east-005.backblazeb2.com',
    ]);

    $admin = backupAdminWith('backups.manage');

    $response = $this->actingAs($admin)
        ->postJson('/api/backups/offsite/test')
        ->assertOk()
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.reachable', true);
});

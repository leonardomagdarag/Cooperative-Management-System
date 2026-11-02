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
        ->assertJsonPath('data.settings.daily_time', '03:00')
        ->assertJsonPath('data.settings.tiers.daily.retention.days', 14)
        ->assertJsonPath('data.settings.tiers.daily.retention.gfs.daily_days', 14)
        ->assertJsonPath('data.settings.tiers.daily.retention.gfs.weekly_weeks', 5)
        ->assertJsonPath('data.settings.tiers.daily.retention.gfs.monthly_months', 12)
        ->assertJsonPath('data.settings.offsite.configured', false)
        ->assertJsonPath('data.settings.offsite.object_lock.enabled', false)
        ->assertJsonStructure([
            'data' => [
                'settings' => ['tiers' => ['daily', 'offsite']],
                'tiers' => ['daily', 'offsite'],
            ],
        ])
        ->assertJsonCount(1, 'data.recent_runs')
        ->assertJsonPath('data.recent_runs.0.size', 123);
});

test('backup index reports the on-disk size of each archive', function () {
    Storage::fake('local_backups');

    $base = (string) config('backup.backup.name');
    Storage::disk('local_backups')->put("{$base}/backup-daily-2026-10-16-01-00-00.zip", 'abcdef');

    $admin = backupAdminWith('backups.view');

    $this->actingAs($admin)
        ->getJson('/api/backups')
        ->assertOk()
        ->assertJsonPath('data.tiers.daily.items.0.name', 'backup-daily-2026-10-16-01-00-00.zip')
        ->assertJsonPath('data.tiers.daily.items.0.size', 6);
});

test('admin can change the daily backup time from settings', function () {
    $admin = backupAdminWith('backups.manage');

    $this->actingAs($admin)
        ->putJson('/api/backups/settings', ['daily_time' => '05:30'])
        ->assertStatus(202)
        ->assertJsonPath('message', 'Backup settings changes are awaiting chairman countersign approval.');
});

test('backup password changes are validated and encrypted in the database', function () {
    $admin = backupAdminWith('backups.manage');

    $this->actingAs($admin)
        ->putJson('/api/backups/settings', [
            'archive_password' => 'StrongPass1!',
            'archive_password_confirmation' => 'StrongPass1!',
        ])
        ->assertStatus(202);

    $setting = SystemSetting::where('key', 'backup_archive_password')->firstOrFail();
    expect($setting->type)->toBe('encrypted')
        ->and($setting->value)->not->toBe('StrongPass1!');
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
            'tier' => 'daily',
            'path' => 'Laravel/backup-daily-test.zip',
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
        ->postJson('/api/backups/daily/run', ['scope' => 'invalid_scope'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Invalid scope.']);
});

test('backups.manage queues a daily files backup', function () {
    config(['backup.backup.password' => 'test-encryption-key']);
    config(['backup.backup.encryption' => 'none']);

    $admin = backupAdminWith('backups.manage');

    $this->actingAs($admin)
        ->postJson('/api/backups/daily/run', ['scope' => 'files'])
        ->assertStatus(202)
        ->assertJsonPath('run.status', 'queued');

    expect(BackupRun::where('tier', 'daily')->latest()->first()->status)->toBe('success');
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

test('cleanup prunes local archives according to GFS retention', function () {
    Storage::fake('local_backups');
    $base = (string) config('backup.backup.name');

    // Create a series of archives:
    // Today: 2 archives (one older)
    Storage::disk('local_backups')->put("{$base}/backup-daily-2026-11-01-03-00-00.zip", 'newest');
    Storage::disk('local_backups')->put("{$base}/backup-daily-2026-11-01-01-00-00.zip", 'older-today');
    // 3 years ago (expired)
    Storage::disk('local_backups')->put("{$base}/backup-daily-2023-01-01-03-00-00.zip", 'expired');
    // Uploaded restore file (must NOT be deleted)
    Storage::disk('local_backups')->put("{$base}/backup-daily-uploaded-20261101-uuid.zip", 'uploaded');

    $admin = backupAdminWith('backups.manage');

    $this->actingAs($admin)
        ->postJson('/api/backups/daily/cleanup')
        ->assertOk()
        ->assertJsonPath('message', 'Cleanup completed.');

    // The older today run and expired run should be deleted
    expect(Storage::disk('local_backups')->exists("{$base}/backup-daily-2026-11-01-03-00-00.zip"))->toBeTrue()
        ->and(Storage::disk('local_backups')->exists("{$base}/backup-daily-2026-11-01-01-00-00.zip"))->toBeFalse()
        ->and(Storage::disk('local_backups')->exists("{$base}/backup-daily-2023-01-01-03-00-00.zip"))->toBeFalse()
        ->and(Storage::disk('local_backups')->exists("{$base}/backup-daily-uploaded-20261101-uuid.zip"))->toBeTrue();
});

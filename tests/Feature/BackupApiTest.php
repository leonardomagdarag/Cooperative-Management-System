<?php

use App\Models\AppCredential;
use App\Models\BackupRun;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);
});

function adminWithPermission(string $permission): User
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
    $admin = adminWithPermission('backups.view');

    BackupRun::create([
        'tier' => 'local',
        'scope' => 'both',
        'status' => 'success',
        'filename' => 'Laravel/backup-test.zip',
        'size' => 123,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson('/api/backups')
        ->assertOk()
        ->assertJsonPath('data.settings.encryption_enabled', true)
        ->assertJsonPath('data.settings.mesh.local.enabled', true)
        ->assertJsonCount(1, 'data.recent_runs');
});

test('admin can update backup settings without cloud config', function () {
    $admin = adminWithPermission('backups.manage');

    $this->actingAs($admin)
        ->putJson('/api/backups/settings', [
            'encryption_enabled' => true,
            'local' => ['enabled' => true, 'schedule' => '0 4 * * *', 'scope' => 'db', 'retention_days' => 3],
            'cloud' => ['enabled' => false, 'schedule' => '0 5 * * 1', 'scope' => 'both', 'retention_days' => 14],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Backup settings updated.');

    expect(SystemSetting::get('backup_local_schedule'))->toBe('0 4 * * *')
        ->and(SystemSetting::get('backup_local_retention_days'))->toBe(3)
        ->and(SystemSetting::get('backup_cloud_enabled'))->toBe(false);
});

test('enabling the cloud tier while unconfigured is rejected', function () {
    config(['filesystems.disks.google.clientId' => null]);

    $admin = adminWithPermission('backups.manage');

    $this->actingAs($admin)
        ->putJson('/api/backups/settings', [
            'encryption_enabled' => true,
            'local' => ['enabled' => true, 'schedule' => '0 4 * * *', 'scope' => 'both', 'retention_days' => 3],
            'cloud' => ['enabled' => true, 'schedule' => '0 5 * * 1', 'scope' => 'both', 'retention_days' => 14],
        ])
        ->assertStatus(422)
        ->assertJson(['code' => 'google_drive_unconfigured']);
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
            'tier' => 'local',
            'path' => 'Laravel/backup.zip',
            'scope' => 'both',
            'current_password' => 'password',
            'confirmation' => 'RESTORE',
            'archive_password' => 'test-key',
        ])
        ->assertStatus(403);
});

test('backups.manage allows running a local files backup that writes an archive', function () {
    config(['backup.backup.password' => 'test-encryption-key']);
    config(['filesystems.disks.google.clientId' => null]);

    $admin = adminWithPermission('backups.manage');

    $this->actingAs($admin)
        ->postJson('/api/backups/local/run', ['scope' => 'files'])
        ->assertStatus(201);

    expect(BackupRun::where('tier', 'local')->latest()->first()->status)->toBe('success');
});

test('cloud credentials status is not exposed without the backups.view permission', function () {
    $member = User::factory()->create([
        'role' => 'member',
        'email' => 'member-c-'.Str::random(8).'@example.com',
        'password' => 'password',
        'account_status' => 'active',
    ]);

    $this->actingAs($member)
        ->getJson('/api/backups/cloud-credentials')
        ->assertStatus(403);
});

test('admin can save Google Drive credentials and they are stored encrypted', function () {
    config(['filesystems.disks.google.clientId' => null]);

    $admin = adminWithPermission('backups.manage');

    $this->actingAs($admin)
        ->putJson('/api/backups/cloud-credentials', [
            'client_id' => 'client-123.apps.googleusercontent.com',
            'client_secret' => 'super-secret',
            'refresh_token' => 'ref-token-abc',
            'folder' => 'Coop Backups',
        ])
        ->assertOk()
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.folder', 'Coop Backups');

    $stored = AppCredential::where('key', 'gdrive_client_secret')->first();
    expect($stored)->not->toBeNull();

    // Value must be encrypted at rest (never plaintext in the table).
    $raw = DB::table('app_credentials')->where('key', 'gdrive_client_secret')->value('value');
    expect($raw)->not->toBe('super-secret')
        ->and(Crypt::decryptString($raw))->toBe('super-secret');

    // The cloud tier becomes configured, so enabling it in settings now works.
    $this->actingAs($admin)
        ->putJson('/api/backups/settings', [
            'encryption_enabled' => true,
            'local' => ['enabled' => true, 'schedule' => '0 4 * * *', 'scope' => 'both', 'retention_days' => 3],
            'cloud' => ['enabled' => true, 'schedule' => '0 5 * * 1', 'scope' => 'both', 'retention_days' => 14],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Backup settings updated.');
});

test('cloud credentials summary never leaks secret values', function () {
    AppCredential::create(['key' => 'gdrive_client_secret', 'value' => 'super-secret']);

    $admin = adminWithPermission('backups.view');

    $response = $this->actingAs($admin)
        ->getJson('/api/backups/cloud-credentials')
        ->assertOk();

    expect($response->json('data'))->not->toHaveKey('client_secret')
        ->and($response->getContent())->not->toContain('super-secret')
        ->and($response->json('data.stored.client_secret'))->toBeTrue();
});

test('admin can remove stored Google Drive credentials', function () {
    AppCredential::create(['key' => 'gdrive_client_id', 'value' => 'client-123']);
    AppCredential::create(['key' => 'gdrive_refresh_token', 'value' => 'token']);

    $admin = adminWithPermission('backups.manage');

    $this->actingAs($admin)
        ->deleteJson('/api/backups/cloud-credentials')
        ->assertOk()
        ->assertJsonPath('data.configured', false);

    expect(AppCredential::count())->toBe(0);
});

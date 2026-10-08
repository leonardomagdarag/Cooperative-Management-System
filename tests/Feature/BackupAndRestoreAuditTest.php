<?php

use App\Models\BackupRun;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    config([
        'filesystems.disks.b2.key' => null,
        'filesystems.disks.b2.secret' => null,
        'filesystems.disks.b2.bucket' => null,
        'filesystems.disks.b2.endpoint' => null,
    ]);
});

function auditAdminWith(array $permissions = []): User
{
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin-'.Str::random(8).'@example.com',
        'password' => bcrypt('password123'),
        'account_status' => 'active',
    ]);
    $admin->syncRoles(['admin']);
    foreach ($permissions as $permission) {
        $admin->givePermissionTo($permission);
    }

    return $admin;
}

test('guest cannot access backup endpoints', function () {
    $this->getJson('/api/backups')->assertStatus(401);
    $this->getJson('/api/backups/runs')->assertStatus(401);
    $this->putJson('/api/backups/settings', [])->assertStatus(401);
    $this->postJson('/api/backups/offsite/test', [])->assertStatus(401);
    $this->postJson('/api/backups/hourly/run', [])->assertStatus(401);
    $this->postJson('/api/backups/hourly/cleanup')->assertStatus(401);
    $this->getJson('/api/backups/hourly/download')->assertStatus(401);
    $this->deleteJson('/api/backups/hourly/backup')->assertStatus(401);
    $this->postJson('/api/backups/restore', [])->assertStatus(401);
});

test('member and unauthorized officers are forbidden from backup endpoints', function () {
    $member = User::factory()->create([
        'role' => 'member',
        'email' => 'member-'.Str::random(8).'@example.com',
        'account_status' => 'active',
    ]);
    $member->syncRoles(['member']);

    $accountant = User::factory()->create([
        'role' => 'accountant',
        'email' => 'sec-'.Str::random(8).'@example.com',
        'account_status' => 'active',
    ]);
    $accountant->syncRoles(['accountant']);

    foreach ([$member, $accountant] as $user) {
        $this->actingAs($user)->getJson('/api/backups')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/backups/runs')->assertStatus(403);
        $this->actingAs($user)->putJson('/api/backups/settings', [])->assertStatus(403);
        $this->actingAs($user)->postJson('/api/backups/offsite/test')->assertStatus(403);
        $this->actingAs($user)->postJson('/api/backups/hourly/run', [])->assertStatus(403);
        $this->actingAs($user)->postJson('/api/backups/restore', [])->assertStatus(403);
    }
});

test('backups settings validation rules', function () {
    $admin = auditAdminWith(['backups.manage']);

    // Empty payload
    $this->actingAs($admin)
        ->putJson('/api/backups/settings', [])
        ->assertStatus(422);

    // Invalid values
    $this->actingAs($admin)
        ->putJson('/api/backups/settings', [
            'encryption_enabled' => 'not-a-boolean',
            'unexpected' => 'ignored field should still allow saving',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['encryption_enabled']);
});

test('run rejects invalid tier or invalid scope', function () {
    $admin = auditAdminWith(['backups.manage']);

    $this->actingAs($admin)
        ->postJson('/api/backups/invalid_tier/run')
        ->assertStatus(422)
        ->assertJson(['message' => 'Invalid tier.']);

    $this->actingAs($admin)
        ->postJson('/api/backups/hourly/run', ['scope' => 'invalid_scope'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Invalid scope.']);
});

test('cleanup rejects invalid tier', function () {
    $admin = auditAdminWith(['backups.manage']);

    $this->actingAs($admin)
        ->postJson('/api/backups/unknown_tier/cleanup')
        ->assertStatus(422)
        ->assertJson(['message' => 'Invalid tier.']);
});

test('restore validation enforces current password and confirmation keyword', function () {
    $admin = auditAdminWith(['backups.restore']);

    // Missing confirmation and invalid current_password
    $this->actingAs($admin)
        ->postJson('/api/backups/restore', [
            'tier' => 'hourly',
            'path' => 'Laravel/test.zip',
            'scope' => 'both',
            'current_password' => 'wrong_password',
            'confirmation' => 'yes',
            'archive_password' => 'secret',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['current_password', 'confirmation']);
});

test('runs endpoint returns paginated backup runs with user attribution', function () {
    $admin = auditAdminWith(['backups.view']);

    BackupRun::create([
        'tier' => 'daily',
        'scope' => 'db',
        'status' => 'success',
        'filename' => 'Laravel/backup-daily-2026.zip',
        'size' => 1024,
        'started_at' => now()->subMinutes(5),
        'finished_at' => now()->subMinutes(4),
        'triggered_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/backups/runs')
        ->assertOk();

    expect($response->json('data.data.0.triggered_by'))->toBe($admin->email)
        ->and($response->json('data.data.0.filename'))->toBe('Laravel/backup-daily-2026.zip');
});

test('web route backup ping enforces token when BACKUP_PING_TOKEN is configured', function () {
    config(['app.backup_ping_token' => 'my-secret-ping-token']);
    putenv('BACKUP_PING_TOKEN=my-secret-ping-token');

    // Without token
    $this->get('/backup/ping')
        ->assertStatus(403);

    // With invalid token
    $this->get('/backup/ping?token=wrong-token')
        ->assertStatus(403);
});

test('off-site connection test records an audit log when configured and reachable', function () {
    Storage::fake('b2');
    config([
        'filesystems.disks.b2.key' => 'key',
        'filesystems.disks.b2.secret' => 'secret',
        'filesystems.disks.b2.region' => 'us-east-005',
        'filesystems.disks.b2.bucket' => 'bucket',
        'filesystems.disks.b2.endpoint' => 'https://s3.us-east-005.backblazeb2.com',
    ]);

    $admin = auditAdminWith(['backups.manage']);

    $this->actingAs($admin)
        ->postJson('/api/backups/offsite/test')
        ->assertOk();

    expect(Activity::where('event', 'backup.offsite_connection_tested')->exists())->toBeTrue();
});

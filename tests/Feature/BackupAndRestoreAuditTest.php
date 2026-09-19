<?php

use Spatie\Activitylog\Models\Activity;
use App\Models\BackupRun;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);
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
    $this->getJson('/api/backups/cloud-credentials')->assertStatus(401);
    $this->putJson('/api/backups/cloud-credentials', [])->assertStatus(401);
    $this->postJson('/api/backups/cloud-credentials/test', [])->assertStatus(401);
    $this->deleteJson('/api/backups/cloud-credentials')->assertStatus(401);
    $this->postJson('/api/backups/local/run', [])->assertStatus(401);
    $this->postJson('/api/backups/local/cleanup')->assertStatus(401);
    $this->getJson('/api/backups/local/download')->assertStatus(401);
    $this->deleteJson('/api/backups/local/backup')->assertStatus(401);
    $this->postJson('/api/backups/restore', [])->assertStatus(401);
});

test('member and unauthorized officers are forbidden from backup endpoints', function () {
    $member = User::factory()->create([
        'role' => 'member',
        'email' => 'member-'.Str::random(8).'@example.com',
        'account_status' => 'active',
    ]);
    $member->syncRoles(['member']);

    $secretary = User::factory()->create([
        'role' => 'secretary',
        'email' => 'sec-'.Str::random(8).'@example.com',
        'account_status' => 'active',
    ]);
    $secretary->syncRoles(['secretary']);

    foreach ([$member, $secretary] as $user) {
        $this->actingAs($user)->getJson('/api/backups')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/backups/runs')->assertStatus(403);
        $this->actingAs($user)->putJson('/api/backups/settings', [])->assertStatus(403);
        $this->actingAs($user)->getJson('/api/backups/cloud-credentials')->assertStatus(403);
        $this->actingAs($user)->postJson('/api/backups/local/run', [])->assertStatus(403);
        $this->actingAs($user)->postJson('/api/backups/restore', [])->assertStatus(403);
    }
});

test('backups settings validation rules', function () {
    $admin = auditAdminWith(['backups.manage']);

    // Empty payload
    $this->actingAs($admin)
        ->putJson('/api/backups/settings', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['local', 'cloud']);

    // Invalid values
    $this->actingAs($admin)
        ->putJson('/api/backups/settings', [
            'local' => [
                'enabled' => true,
                'schedule' => '',
                'scope' => 'invalid_scope',
                'retention_days' => 0, // min is 1
            ],
            'cloud' => [
                'enabled' => false,
                'schedule' => '0 5 * * *',
                'scope' => 'db',
                'retention_days' => 5000, // max is 3650
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'local.schedule',
            'local.scope',
            'local.retention_days',
            'cloud.retention_days',
        ]);
});

test('run rejects invalid tier or invalid scope', function () {
    $admin = auditAdminWith(['backups.manage']);

    $this->actingAs($admin)
        ->postJson('/api/backups/invalid_tier/run')
        ->assertStatus(422)
        ->assertJson(['message' => 'Invalid tier.']);

    $this->actingAs($admin)
        ->postJson('/api/backups/local/run', ['scope' => 'invalid_scope'])
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
            'tier' => 'local',
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
        'tier' => 'local',
        'scope' => 'db',
        'status' => 'success',
        'filename' => 'Laravel/backup-2026.zip',
        'size' => 1024,
        'started_at' => now()->subMinutes(5),
        'finished_at' => now()->subMinutes(4),
        'triggered_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/backups/runs')
        ->assertOk();

    expect($response->json('data.data.0.triggered_by'))->toBe($admin->email)
        ->and($response->json('data.data.0.filename'))->toBe('Laravel/backup-2026.zip');
});

test('cloud credentials operations record audit logs', function () {
    $admin = auditAdminWith(['backups.manage']);

    // Update
    $this->actingAs($admin)
        ->putJson('/api/backups/cloud-credentials', [
            'client_id' => 'client-xyz',
            'client_secret' => 'secret-xyz',
            'refresh_token' => 'token-xyz',
            'folder' => 'Backups',
        ])
        ->assertOk();

    expect(Activity::where('event', 'backup.cloud_credentials_updated')->exists())->toBeTrue();

    // Clear
    $this->actingAs($admin)
        ->deleteJson('/api/backups/cloud-credentials')
        ->assertOk();

    expect(Activity::where('event', 'backup.cloud_credentials_cleared')->exists())->toBeTrue();
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

<?php

use App\Jobs\RestoreBackupJob;
use App\Jobs\SendEmailJob;
use App\Mail\BackupAlertMail;
use App\Models\BackupRun;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\BackupNotificationService;
use App\Services\BackupService;
use App\Services\CountersignService;
use App\Support\MaintenanceState;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

test('backup operation lock survives clearing the default cache', function () {
    $name = 'backup-operation-test-'.Str::random(12);
    $first = Cache::store('backup_operations')->lock($name, 60);
    $second = Cache::store('backup_operations')->lock($name, 60);

    try {
        expect($first->get())->toBeTrue();
        Cache::flush();
        expect($second->get())->toBeFalse();
    } finally {
        $first->release();
    }
});

test('restore checkpoint is built before its run row and keeps both database and files', function () {
    $base = (string) config('backup.backup.name');
    Storage::fake('local_backups');
    SystemSetting::set('backup_archive_password', 'test-archive-password');
    $runCount = BackupRun::query()->count();

    $audit = Mockery::mock(AuditLogService::class);
    $audit->shouldReceive('log')->once()->with('backup.restore_checkpoint.created', Mockery::any(), 'backup', Mockery::type(BackupRun::class), null, Mockery::type('array'));
    $notifications = Mockery::mock(BackupNotificationService::class);
    $service = new BackupService($audit, $notifications);

    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run', Mockery::on(function (array $options) use ($base, $runCount) {
            expect(BackupRun::query()->count())->toBe($runCount);
            expect($options)->toHaveKey('--only-to-disk', 'local_backups')
                ->and($options)->not->toHaveKey('--only-db')
                ->and($options)->not->toHaveKey('--only-files');

            $prefix = (string) config('backup.backup.destination.filename_prefix');
            Storage::disk('local_backups')->put("{$base}/{$prefix}2026-11-02-10-00-00.zip", 'checkpoint');

            return true;
        }))
        ->andReturn(0);

    $checkpoint = $service->createRestoreCheckpoint();

    expect($checkpoint->fresh()->scope)->toBe('both')
        ->and($checkpoint->status)->toBe('success')
        ->and(Storage::disk('local_backups')->exists($checkpoint->filename))->toBeTrue();
});

test('restore lifecycle notices are emailed to the captured administrators', function () {
    Mail::fake();

    app(BackupNotificationService::class)->notifyRestoreEvent(
        ['restore-admin@example.com'],
        'Restore safety checkpoint created',
        'The current database and files were saved.',
        ['restore_run_id' => '42'],
    );

    Mail::assertSent(BackupAlertMail::class, fn (BackupAlertMail $mail) => $mail->hasTo('restore-admin@example.com')
            && $mail->title === 'Restore safety checkpoint created'
            && $mail->details['restore_run_id'] === '42'
    );
});

test('terminal restore worker failures notify admins while maintenance is active', function () {
    $notifications = Mockery::mock(BackupNotificationService::class);
    $notifications->shouldReceive('notifyRestoreEvent')
        ->once()
        ->with(['restore-admin@example.com'], 'Restore worker failed while the system was offline', Mockery::any(), Mockery::type('array'));
    app()->instance(BackupNotificationService::class, $notifications);
    app()->maintenanceMode()->activate(['time' => time(), 'retry' => 15]);
    MaintenanceState::enter(MaintenanceState::REASON_RESTORE);

    try {
        (new RestoreBackupJob(42, 'encrypted-password', ['restore-admin@example.com']))
            ->failed(new RuntimeException('Worker timed out'));
    } finally {
        app()->maintenanceMode()->deactivate();
        MaintenanceState::leave();
    }
});

test('guest cannot access backup endpoints', function () {
    $this->getJson('/api/backups')->assertStatus(401);
    $this->getJson('/api/backups/runs')->assertStatus(401);
    $this->putJson('/api/backups/settings', [])->assertStatus(401);
    $this->postJson('/api/backups/offsite/test', [])->assertStatus(401);
    $this->postJson('/api/backups/daily/run', [])->assertStatus(401);
    $this->postJson('/api/backups/daily/cleanup')->assertStatus(401);
    $this->getJson('/api/backups/daily/download')->assertStatus(401);
    $this->deleteJson('/api/backups/daily/backup')->assertStatus(401);
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
        $this->actingAs($user)->postJson('/api/backups/daily/run', [])->assertStatus(403);
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
            'daily_time' => 'not-a-time',
            'unexpected' => 'ignored field should still allow saving',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['daily_time']);
});

test('run rejects invalid tier or invalid scope', function () {
    $admin = auditAdminWith(['backups.manage']);

    $this->actingAs($admin)
        ->postJson('/api/backups/invalid_tier/run')
        ->assertStatus(422)
        ->assertJson(['message' => 'Invalid tier.']);

    $this->actingAs($admin)
        ->postJson('/api/backups/daily/run', ['scope' => 'invalid_scope'])
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
            'tier' => 'daily',
            'path' => 'Laravel/test.zip',
            'scope' => 'both',
            'current_password' => 'wrong_password',
            'confirmation' => 'yes',
            'archive_password' => 'secret',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['current_password', 'confirmation']);
});

test('a failed database restore leaves the app in maintenance mode', function () {
    $base = (string) config('backup.backup.name');
    $path = "{$base}/backup-daily-test.zip";
    Storage::fake('local_backups');
    Mail::fake();
    Storage::disk('local_backups')->put($path, 'test archive');
    SystemSetting::set('backup_archive_password', 'test-archive-password');

    $audit = Mockery::mock(AuditLogService::class);
    $audit->shouldReceive('log')->once()->with('backup.restore_failed', Mockery::any(), 'backup', null, null, Mockery::type('array'));
    $notifications = Mockery::mock(BackupNotificationService::class);
    $notifications->shouldReceive('notifyRestoreEvent')->twice();
    $service = new BackupService($audit, $notifications);

    Artisan::shouldReceive('call')
        ->once()
        ->with('down', Mockery::on(fn (array $options) => isset($options['--secret'])))
        ->andReturnUsing(function () {
            app()->maintenanceMode()->activate(['time' => time(), 'retry' => 15]);

            return 0;
        });
    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:restore', Mockery::type('array'))
        ->andReturn(1);
    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:restore', Mockery::type('array'))
        ->andReturn(1);
    Artisan::shouldReceive('call')->never()->with('up', Mockery::any());
    Artisan::shouldReceive('output')->twice()->andReturn('simulated import failure', 'simulated rollback failure');

    try {
        expect(fn () => $service->restore('daily', $path, 'db', 'test-archive-password', null, $path, ['admin@example.com']))
            ->toThrow(RuntimeException::class, 'Database restore failed');

        expect(app()->maintenanceMode()->active())->toBeTrue()
            ->and(MaintenanceState::current()['reason'])->toBe(MaintenanceState::REASON_RESTORE_FAILED);
    } finally {
        app()->maintenanceMode()->deactivate();
        MaintenanceState::leave();
    }
});

test('a failed target restore is rolled back to the safety checkpoint', function () {
    Sleep::fake();
    $base = (string) config('backup.backup.name');
    $path = "{$base}/backup-daily-test.zip";
    Storage::fake('local_backups');
    Storage::disk('local_backups')->put($path, 'test archive');
    SystemSetting::set('backup_archive_password', 'test-archive-password');

    $audit = Mockery::mock(AuditLogService::class);
    $audit->shouldReceive('log')->once()->with('backup.restore_failed', Mockery::any(), 'backup', null, null, Mockery::type('array'));
    $notifications = Mockery::mock(BackupNotificationService::class);
    $notifications->shouldReceive('notifyRestoreEvent')->twice();
    $service = new BackupService($audit, $notifications);
    $restoreAttempts = 0;

    Artisan::shouldReceive('call')->andReturnUsing(function (string $command) use (&$restoreAttempts) {
        if ($command === 'down') {
            app()->maintenanceMode()->activate(['time' => time(), 'retry' => 15]);
        } elseif ($command === 'backup:restore') {
            $restoreAttempts++;

            return $restoreAttempts === 1 ? 1 : 0;
        } elseif ($command === 'up') {
            Sleep::assertSlept(fn ($duration) => $duration->totalSeconds > 0 && $duration->totalSeconds <= 30);
            app()->maintenanceMode()->deactivate();
        }

        return 0;
    });
    Artisan::shouldReceive('output')->once()->andReturn('simulated target restore failure');

    try {
        expect(fn () => $service->restore('daily', $path, 'db', 'test-archive-password', null, $path, ['admin@example.com']))
            ->toThrow(RuntimeException::class, 'Database restore failed');

        expect($restoreAttempts)->toBe(2)
            ->and(app()->maintenanceMode()->active())->toBeFalse()
            ->and(MaintenanceState::isActive())->toBeFalse();
    } finally {
        app()->maintenanceMode()->deactivate();
        MaintenanceState::leave();
        Sleep::fake(false);
    }
});

test('a successful restore remains offline for at least thirty seconds before reopening', function () {
    Sleep::fake();
    $base = (string) config('backup.backup.name');
    $path = "{$base}/backup-daily-test.zip";
    Storage::fake('local_backups');
    Storage::disk('local_backups')->put($path, 'test archive');
    SystemSetting::set('backup_archive_password', 'test-archive-password');

    $audit = Mockery::mock(AuditLogService::class);
    $audit->shouldReceive('log')->once()->with('backup.restored', Mockery::any(), 'backup', null, null, Mockery::type('array'));
    $notifications = Mockery::mock(BackupNotificationService::class);
    $notifications->shouldReceive('notifyRestoreEvent')->once();
    $service = new BackupService($audit, $notifications);

    Artisan::shouldReceive('call')->andReturnUsing(function (string $command) {
        if ($command === 'down') {
            app()->maintenanceMode()->activate(['time' => time(), 'retry' => 15]);
        } elseif ($command === 'up') {
            expect(app()->maintenanceMode()->active())->toBeTrue();
            Sleep::assertSlept(fn ($duration) => $duration->totalSeconds > 0 && $duration->totalSeconds <= 30);
            app()->maintenanceMode()->deactivate();
        }

        return 0;
    });

    try {
        $service->restore('daily', $path, 'db', 'test-archive-password', null, $path, ['admin@example.com']);

        expect(app()->maintenanceMode()->active())->toBeFalse()
            ->and(MaintenanceState::isActive())->toBeFalse();
    } finally {
        app()->maintenanceMode()->deactivate();
        MaintenanceState::leave();
        Sleep::fake(false);
    }
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

test('backup failure and download reminders queue administrator emails', function () {
    Queue::fake();
    $admin = auditAdminWith();

    $failedRun = BackupRun::create([
        'tier' => 'daily',
        'scope' => 'both',
        'status' => 'failed',
        'message' => 'Backup command failed.',
        'finished_at' => now(),
    ]);

    app(BackupNotificationService::class)->notifyFailure($failedRun);

    $successfulRun = BackupRun::create([
        'tier' => 'daily',
        'scope' => 'both',
        'status' => 'success',
        'filename' => 'Laravel/backup-daily-latest.zip',
        'size' => 1024,
        'finished_at' => now()->subDay(),
    ]);
    SystemSetting::set('backup_download_reminder_biweekly_last_sent', now()->subDays(15)->toDateTimeString(), 'string', 'backup');
    SystemSetting::set('backup_download_reminder_monthly_last_sent', now()->subMonth()->subDay()->toDateTimeString(), 'string', 'backup');

    $this->artisan('backup:notify-admin')->assertSuccessful();

    Queue::assertPushed(SendEmailJob::class, app(BackupNotificationService::class)->administratorEmails()->count() * 3);
    expect($admin->email)->toBeString()
        ->and($successfulRun->filename)->toBe('Laravel/backup-daily-latest.zip');
});

test('manual deletion of backups is permanently disabled for all users including administrators', function () {
    $admin = auditAdminWith(['backups.manage']);

    $response = $this->actingAs($admin)
        ->deleteJson('/api/backups/daily/backup?file=Laravel/backup-daily-test.zip');

    $response->assertStatus(403);
    expect($response->json('message'))->toContain('permanently disabled');
});

test('countersign request rejects backup.delete action', function () {
    $admin = auditAdminWith(['backups.manage']);

    $this->expectException(HttpException::class);
    $this->expectExceptionMessage('Deleting backups is permanently disabled for security reasons.');

    app(CountersignService::class)->request('backup.delete', [
        'tier' => 'daily',
        'path' => 'Laravel/backup-daily-test.zip',
    ], $admin);
});

test('backup service delete method throws exception', function () {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Deleting backups is permanently disabled for security reasons.');

    app(BackupService::class)->delete('daily', 'Laravel/backup-daily-test.zip');
});

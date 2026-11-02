<?php

use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    Mail::fake();
});

test('restore drill refuses an unconfigured verification database without maintenance', function () {
    config([
        'database.connections.restore_verification.database' => null,
        'database.connections.restore_verification.username' => null,
        'database.connections.restore_verification.password' => null,
    ]);

    expect(Artisan::call('backup:verify-restore'))->toBe(1)
        ->and(app()->maintenanceMode()->active())->toBeFalse()
        ->and(Activity::where('event', 'backup.restore_verification_failed')->exists())->toBeTrue();
});

test('restore drill refuses production database and production account before connecting', function () {
    config(['database.connections.sqlite.username' => 'production_test_user']);
    $production = config('database.connections.'.config('database.default'));
    config([
        'database.connections.restore_verification.database' => $production['database'],
        'database.connections.restore_verification.username' => 'verification_only',
        'database.connections.restore_verification.password' => 'test-secret',
    ]);
    expect(Artisan::call('backup:verify-restore'))->toBe(1);

    config([
        'database.connections.restore_verification.database' => 'disposable_restore_check',
        'database.connections.restore_verification.username' => $production['username'],
    ]);
    expect(Artisan::call('backup:verify-restore'))->toBe(1)
        ->and(app()->maintenanceMode()->active())->toBeFalse();
});

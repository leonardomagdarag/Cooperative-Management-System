<?php

use App\Services\Backup\GfsRetentionService;
use Carbon\Carbon;

test('parses archive dates from standard backup filenames', function () {
    $date = GfsRetentionService::parseArchiveDate('backup-daily-2026-10-15-03-00-00.zip');
    expect($date->format('Y-m-d H:i:s'))->toBe('2026-10-15 03:00:00');

    $offsiteDate = GfsRetentionService::parseArchiveDate('backup-offsite-2026-05-01-12-30-45.zip');
    expect($offsiteDate->format('Y-m-d H:i:s'))->toBe('2026-05-01 12:30:45');

    $fallback = GfsRetentionService::parseArchiveDate('unknown-format.zip', 1700000000);
    expect($fallback->timestamp)->toBe(1700000000);
});

test('applies grandfather-father-son retention correctly', function () {
    $now = Carbon::parse('2026-11-01 03:00:00');
    $service = new GfsRetentionService(dailyDays: 14, weeklyWeeks: 5, monthlyMonths: 12);

    $archives = collect();

    // 1. Daily period (0-13 days ago): 2 backups on today, 1 backup on each of past 5 days
    $archives->push([
        'path' => 'Laravel/backup-daily-2026-11-01-03-00-00.zip',
        'date' => Carbon::parse('2026-11-01 03:00:00'),
    ]);
    $archives->push([
        'path' => 'Laravel/backup-daily-2026-11-01-01-00-00.zip', // Older today run -> delete
        'date' => Carbon::parse('2026-11-01 01:00:00'),
    ]);
    $archives->push([
        'path' => 'Laravel/backup-daily-2026-10-31-03-00-00.zip',
        'date' => Carbon::parse('2026-10-31 03:00:00'),
    ]);
    $archives->push([
        'path' => 'Laravel/backup-daily-2026-10-25-03-00-00.zip',
        'date' => Carbon::parse('2026-10-25 03:00:00'),
    ]);

    // 2. Weekly period (between 14 days and 14 days + 5 weeks ago)
    // 2026-11-01 minus 20 days is ~2026-10-12 (Week 42). Two backups in Week 42:
    $archives->push([
        'path' => 'Laravel/backup-daily-2026-10-14-03-00-00.zip', // Keep newest of week
        'date' => Carbon::parse('2026-10-14 03:00:00'),
    ]);
    $archives->push([
        'path' => 'Laravel/backup-daily-2026-10-13-03-00-00.zip', // Same week -> delete
        'date' => Carbon::parse('2026-10-13 03:00:00'),
    ]);

    // 3. Monthly period (between weekly cutoff and 12 months ago)
    // 2026-08 (August): Two backups
    $archives->push([
        'path' => 'Laravel/backup-daily-2026-08-20-03-00-00.zip', // Keep newest of month
        'date' => Carbon::parse('2026-08-20 03:00:00'),
    ]);
    $archives->push([
        'path' => 'Laravel/backup-daily-2026-08-01-03-00-00.zip', // Older same month -> delete
        'date' => Carbon::parse('2026-08-01 03:00:00'),
    ]);

    // 4. Expired (> 12 months before weekly cutoff, e.g. 2 years ago)
    $archives->push([
        'path' => 'Laravel/backup-daily-2024-01-01-03-00-00.zip', // Expired -> delete
        'date' => Carbon::parse('2024-01-01 03:00:00'),
    ]);

    // 5. Staged restore file
    $archives->push([
        'path' => 'Laravel/backup-daily-uploaded-20260101-uuid.zip',
        'date' => Carbon::parse('2025-01-01 03:00:00'),
    ]);

    $plan = $service->planPruning($archives, $now);

    $keptPaths = $plan['keep']->pluck('path')->all();
    $deletedPaths = $plan['delete']->pluck('path')->all();

    // Verify Daily:
    expect($keptPaths)->toContain('Laravel/backup-daily-2026-11-01-03-00-00.zip')
        ->and($deletedPaths)->toContain('Laravel/backup-daily-2026-11-01-01-00-00.zip')
        ->and($keptPaths)->toContain('Laravel/backup-daily-2026-10-31-03-00-00.zip')
        ->and($keptPaths)->toContain('Laravel/backup-daily-2026-10-25-03-00-00.zip');

    // Verify Weekly:
    expect($keptPaths)->toContain('Laravel/backup-daily-2026-10-14-03-00-00.zip')
        ->and($deletedPaths)->toContain('Laravel/backup-daily-2026-10-13-03-00-00.zip');

    // Verify Monthly:
    expect($keptPaths)->toContain('Laravel/backup-daily-2026-08-20-03-00-00.zip')
        ->and($deletedPaths)->toContain('Laravel/backup-daily-2026-08-01-03-00-00.zip');

    // Verify Expired:
    expect($deletedPaths)->toContain('Laravel/backup-daily-2024-01-01-03-00-00.zip');

    // Verify Uploaded Archive is always kept:
    expect($keptPaths)->toContain('Laravel/backup-daily-uploaded-20260101-uuid.zip');
});

test('never deletes the newest backup even if it falls beyond standard windows', function () {
    $now = Carbon::parse('2026-11-01 03:00:00');
    $service = new GfsRetentionService(dailyDays: 7, weeklyWeeks: 2, monthlyMonths: 1);

    // Only one very old backup exists
    $archives = collect([
        [
            'path' => 'Laravel/backup-daily-2020-01-01-03-00-00.zip',
            'date' => Carbon::parse('2020-01-01 03:00:00'),
        ],
    ]);

    $plan = $service->planPruning($archives, $now);

    expect($plan['keep']->pluck('path')->all())->toContain('Laravel/backup-daily-2020-01-01-03-00-00.zip')
        ->and($plan['delete'])->toBeEmpty();
});

test('categorizes archives into daily, weekly, and monthly tiers', function () {
    $now = Carbon::parse('2026-11-01 03:00:00');
    $service = new GfsRetentionService(dailyDays: 14, weeklyWeeks: 5, monthlyMonths: 12);

    // 5 days ago -> daily
    expect($service->categorizeArchive(Carbon::parse('2026-10-27 03:00:00'), $now))->toBe('daily');

    // 20 days ago -> weekly
    expect($service->categorizeArchive(Carbon::parse('2026-10-12 03:00:00'), $now))->toBe('weekly');

    // 3 months ago -> monthly
    expect($service->categorizeArchive(Carbon::parse('2026-08-01 03:00:00'), $now))->toBe('monthly');
});

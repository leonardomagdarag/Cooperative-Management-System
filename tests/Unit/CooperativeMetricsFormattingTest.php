<?php

use App\Services\CooperativeMetricsService;

test('counts stay exact below one thousand and round down with a plus above it', function () {
    expect(CooperativeMetricsService::formatCount(567))->toBe('567')
        ->and(CooperativeMetricsService::formatCount(999))->toBe('999')
        ->and(CooperativeMetricsService::formatCount(1000))->toBe('1,000+')
        ->and(CooperativeMetricsService::formatCount(1248))->toBe('1,200+');
});

test('service volume uses a coarse k suffix above one thousand', function () {
    expect(CooperativeMetricsService::formatVolume(3))->toBe('3')
        ->and(CooperativeMetricsService::formatVolume(999))->toBe('999')
        ->and(CooperativeMetricsService::formatVolume(1000))->toBe('1k+')
        ->and(CooperativeMetricsService::formatVolume(10_000))->toBe('10k+')
        ->and(CooperativeMetricsService::formatVolume(9847))->toBe('9k+');
});

test('assets are published coarsely in millions and thousands', function () {
    expect(CooperativeMetricsService::formatAssets(2_500_000))->toBe('₱2M+')
        ->and(CooperativeMetricsService::formatAssets(24_310_908.55))->toBe('₱24M+')
        ->and(CooperativeMetricsService::formatAssets(500_000))->toBe('₱500K+')
        ->and(CooperativeMetricsService::formatAssets(5_000))->toBe('₱5,000')
        ->and(CooperativeMetricsService::formatAssets(0))->toBe('₱0');
});

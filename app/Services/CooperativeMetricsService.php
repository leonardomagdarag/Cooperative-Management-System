<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\SiteContentCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Live, cooperative-wide metrics for the public home page.
 *
 * These values are published on an unauthenticated endpoint, so they are
 * deliberately aggregate-only (no PII, no per-branch detail), cached, and
 * formatted coarsely. Any failure yields null so the caller falls back to the
 * manually entered content instead of exposing a broken or empty figure.
 */
class CooperativeMetricsService
{
    public const CACHE_KEY = 'public.cooperative_metrics';

    private const CACHE_TTL_SECONDS = 600;

    /**
     * Return the formatted public metrics, or null when live metrics are
     * disabled or the data cannot be read safely.
     *
     * @return array{active_members: ?string, total_assets: ?string, years_service: ?string, service_done: ?string}|null
     */
    public function publicMetrics(): ?array
    {
        if (! $this->liveMetricsEnabled()) {
            return null;
        }

        try {
            return Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL_SECONDS,
                fn (): array => $this->compute(),
            );
        } catch (Throwable) {
            return null;
        }
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function liveMetricsEnabled(): bool
    {
        try {
            return (bool) data_get(SiteContentCatalog::editorContent(), 'home.stats_use_cooperative', false);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{active_members: ?string, total_assets: ?string, years_service: ?string, service_done: ?string}
     */
    private function compute(): array
    {
        $foundingYear = (int) data_get(SiteContentCatalog::editorContent(), 'home.founding_year', 0);

        $members = $this->activeMembers();
        $assets = $this->totalAssets();
        $years = $this->yearsOfService($foundingYear);
        $services = $this->servicesDone();

        return [
            'active_members' => $members > 0 ? self::formatCount($members) : null,
            'total_assets' => $assets > 0 ? self::formatAssets($assets) : null,
            'years_service' => $years > 0 ? (string) $years : null,
            'service_done' => $services > 0 ? self::formatVolume($services) : null,
        ];
    }

    private function activeMembers(): int
    {
        if (! Schema::hasTable('users')) {
            return 0;
        }

        return (int) User::query()
            ->memberUsers()
            ->where('account_status', 'active')
            ->count();
    }

    private function totalAssets(): float
    {
        if (! Schema::hasTable('accounts')) {
            return 0.0;
        }

        return (float) Account::query()
            ->whereIn('account_type', ['savings', 'share-capital'])
            ->sum('balance');
    }

    private function servicesDone(): int
    {
        if (! Schema::hasTable('transactions')) {
            return 0;
        }

        return (int) Transaction::query()
            ->where('status', 'completed')
            ->count();
    }

    private function yearsOfService(int $foundingYear): int
    {
        if ($foundingYear <= 0) {
            return 0;
        }

        return max(0, (int) now()->year - $foundingYear);
    }

    public static function formatCount(int $value): string
    {
        if ($value < 1000) {
            return (string) $value;
        }

        return number_format((int) (floor($value / 100) * 100)).'+';
    }

    public static function formatVolume(int $value): string
    {
        if ($value < 1000) {
            return (string) $value;
        }

        return number_format((int) floor($value / 1000)).'k+';
    }

    public static function formatAssets(float $value): string
    {
        if ($value >= 1_000_000) {
            return '₱'.number_format((int) floor($value / 1_000_000)).'M+';
        }

        if ($value >= 100_000) {
            return '₱'.number_format((int) (floor($value / 100_000) * 100)).'K+';
        }

        return '₱'.number_format((int) $value);
    }
}

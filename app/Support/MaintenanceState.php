<?php

namespace App\Support;

use App\Models\SystemSetting;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Why the site is unavailable, kept in a plain file rather than the cache.
 *
 * The cache store is the database (CACHE_STORE=database), and the case that
 * matters most -- a restore -- is the case where the database is dropped and
 * re-imported. Anything written to the cache while that happens is either lost
 * or unrecoverable, so the state lives in storage/app instead, which no part
 * of a restore touches.
 *
 * Laravel's own maintenance mode (storage/framework/down) still decides whether
 * requests are blocked; this only explains why, so the page can say
 * "restoring" instead of a generic outage.
 */
final class MaintenanceState
{
    public const REASON_RESTORE = 'restore';

    public const REASON_RESTORE_ROLLBACK = 'restore_rollback';

    public const REASON_RESTORE_FAILED = 'restore_failed';

    public const REASON_LOGIN = 'login_disabled';

    public const REASON_SCHEDULED = 'scheduled';

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_MEMBER = 'member';

    public const AUDIENCE_OFFICER = 'officer';

    /**
     * A long restore is normal -- RestoreBackupJob allows an hour, and a large
     * database legitimately takes longer. Past this the flag is assumed to be
     * debris from a process that died between entering maintenance mode and
     * leaving it, so the copy falls back to a generic outage. The page still
     * renders: Laravel's maintenance file is what blocks requests.
     */
    private const STALE_AFTER_HOURS = 12;

    public const DEFAULT_RETRY_AFTER = 15;

    /**
     * Record why maintenance is active. Overwrites any previous state.
     *
     * @param  array{audience?: ?string, retry_after?: ?int}  $context
     */
    public static function enter(string $reason, array $context = []): void
    {
        self::write([
            'reason' => $reason,
            'audience' => $context['audience'] ?? null,
            'retry_after' => $context['retry_after'] ?? self::DEFAULT_RETRY_AFTER,
            'started_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Reconcile the recorded notice with the login switches as they stand now.
     *
     * The middleware and the login endpoint only ever *add* to the state, so a
     * visitor who opened /maintenance directly, or an admin who edited the
     * switch in the database, would be told the wrong thing. This re-derives the
     * answer from the settings themselves.
     *
     * Must not run where the database may be unavailable -- an in-progress
     * restore replaces it. A live restore outranks the login switches and is
     * left alone; a stale one is discarded first, so it cannot mask a switch
     * that is genuinely off.
     */
    public static function syncLoginAvailability(): void
    {
        $current = self::current();

        if ($current !== null && in_array($current['reason'], [self::REASON_RESTORE, self::REASON_RESTORE_ROLLBACK, self::REASON_RESTORE_FAILED], true)) {
            if ($current['reason'] === self::REASON_RESTORE_FAILED) {
                return;
            }

            if (! self::isStale($current['started_at'])) {
                return;
            }

            self::leave();
        }

        $global = (bool) SystemSetting::get('service_login_enabled', true);
        $member = (bool) SystemSetting::get('service_login_member_enabled', true);
        $officer = (bool) SystemSetting::get('service_login_officer_enabled', true);

        if (! $global) {
            self::enterLoginUnavailable(self::AUDIENCE_ALL);

            return;
        }

        if (! $member || ! $officer) {
            // Whichever audience is still switched off is the one being turned away.
            self::enterLoginUnavailable($member ? self::AUDIENCE_OFFICER : self::AUDIENCE_MEMBER);

            return;
        }

        self::leave();
    }

    /**
     * Record that sign-in is switched off, without losing the original timestamp.
     *
     * Called from the middleware that guards /login, so it runs on every request
     * for as long as the outage lasts. Writing unconditionally would keep pushing
     * started_at forward and the page could never say how long sign-in has been
     * down, so the write only happens when the audience actually changed.
     *
     * @param  string|null  $audience  self::AUDIENCE_ALL|MEMBER|OFFICER, or null when unknown.
     */
    public static function enterLoginUnavailable(?string $audience = null): void
    {
        $audience ??= self::AUDIENCE_ALL;
        $current = self::current();

        if ($current !== null
            && $current['reason'] === self::REASON_LOGIN
            && $current['audience'] === $audience) {
            return;
        }

        self::enter(self::REASON_LOGIN, ['audience' => $audience]);
    }

    /**
     * Clear the state. Safe to call when nothing was recorded.
     */
    public static function leave(): void
    {
        $path = self::path();

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * The recorded state, or null when there is none or it is unreadable.
     *
     * This runs while the application is down and possibly while the database
     * is mid-restore, so it must never throw.
     *
     * @return array{reason: string, audience: ?string, retry_after: int, started_at: string}|null
     */
    public static function current(): ?array
    {
        $path = self::path();

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! isset($decoded['reason']) || ! is_string($decoded['reason'])) {
            return null;
        }

        $retryAfter = $decoded['retry_after'] ?? self::DEFAULT_RETRY_AFTER;

        return [
            'reason' => $decoded['reason'],
            'audience' => isset($decoded['audience']) && is_string($decoded['audience'])
                ? $decoded['audience']
                : null,
            'retry_after' => is_numeric($retryAfter) ? max(5, (int) $retryAfter) : self::DEFAULT_RETRY_AFTER,
            'started_at' => isset($decoded['started_at']) && is_string($decoded['started_at'])
                ? $decoded['started_at']
                : now()->toIso8601String(),
        ];
    }

    public static function isActive(): bool
    {
        return self::current() !== null;
    }

    /**
     * Resolve the current state into the copy the page renders.
     *
     * Both entry points (the /maintenance route and the 503 error view) go
     * through here so a restore, a login switch-off and a plain `artisan down`
     * can never disagree about what the visitor is told.
     *
     * @return array{reason: string, headline: string, message: string, detail: ?string, retry_after: int, started_at: ?string, stale: bool}
     */
    public static function describe(): array
    {
        $state = self::current();
        $reason = $state['reason'] ?? self::REASON_SCHEDULED;
        $audience = $state['audience'] ?? null;
        $startedAt = $state['started_at'] ?? null;
        $stale = false;

        // Debris from a process that died mid-restore should read as a plain
        // outage: "restoring since Tuesday" is worse copy than no claim at all.
        if ($startedAt !== null
            && $reason !== self::REASON_RESTORE_FAILED
            && self::isStale($startedAt)) {
            $stale = true;
            $reason = self::REASON_SCHEDULED;
            $audience = null;
        }

        $copy = match ($reason) {
            self::REASON_RESTORE => [
                'headline' => 'We are restoring your data',
                'message' => 'A backup restore is underway and the entire cooperative management system is offline while it runs. Nobody can sign in or transact until it finishes, so this will stay up for the whole duration rather than only for members.',
                'detail' => 'The system is being returned to a recent verified archive, so transactions and balances may change once it finishes. If a restore is interrupted it can stay offline until it is resolved.',
            ],
            self::REASON_RESTORE_ROLLBACK => [
                'headline' => 'Recovering the previous system data',
                'message' => 'The requested restore did not complete. The system is offline while its pre-restore checkpoint is being recovered.',
                'detail' => 'Please wait while the cooperative restores the data that was in place before this operation.',
            ],
            self::REASON_RESTORE_FAILED => [
                'headline' => 'Restore interrupted — system offline',
                'message' => 'A backup restore did not finish successfully. The system is staying offline while the restored data is checked and recovered.',
                'detail' => 'Please contact the system administrator. The system will remain unavailable until recovery is complete.',
            ],
            self::REASON_LOGIN => self::loginCopy($audience),
            default => [
                'headline' => 'Scheduled maintenance',
                'message' => 'The cooperative is carrying out scheduled work on the system. It will be available again shortly.',
            ],
        };

        return [
            'reason' => $reason,
            'headline' => $copy['headline'],
            'message' => $copy['message'],
            'detail' => $copy['detail'] ?? null,
            'retry_after' => $state['retry_after'] ?? self::DEFAULT_RETRY_AFTER,
            'started_at' => $startedAt,
            'stale' => $stale,
        ];
    }

    /**
     * @return array{headline: string, message: string, detail: ?string}
     */
    private static function loginCopy(?string $audience): array
    {
        return match ($audience) {
            self::AUDIENCE_MEMBER => [
                'headline' => 'Member sign-in is temporarily unavailable',
                'message' => 'The cooperative has paused member sign-in while it performs scheduled work. Please try again shortly.',
                'detail' => 'Cooperative officers are not affected and can still sign in.',
            ],
            self::AUDIENCE_OFFICER => [
                'headline' => 'Officer sign-in is temporarily unavailable',
                'message' => 'The cooperative has paused officer sign-in while it performs scheduled work. Please try again shortly.',
                'detail' => 'Members are not affected and can still sign in.',
            ],
            default => [
                'headline' => 'Sign-in is temporarily unavailable',
                'message' => 'The cooperative has switched off sign-in while it performs scheduled work. Please try again shortly.',
                'detail' => 'Members and officers are both affected.',
            ],
        };
    }

    private static function isStale(string $startedAt): bool
    {
        try {
            $started = CarbonImmutable::parse($startedAt);
        } catch (Throwable) {
            // Carbon 2 returns false and Carbon 3 throws; treat both as unusable
            // rather than letting either escape while the application is down.
            return true;
        }

        return $started->isBefore(now()->subHours(self::STALE_AFTER_HOURS));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function write(array $state): void
    {
        $path = self::path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        // Lock before truncating so a concurrent reader never sees a half-written
        // file, which current() would reject and report as "no reason recorded".
        @file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private static function path(): string
    {
        return storage_path('app/maintenance-state.json');
    }
}

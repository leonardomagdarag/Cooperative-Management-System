<?php

namespace App\Services\Backup;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GfsRetentionService
{
    /**
     * @param  int  $dailyDays  Number of days of daily backups to keep (Son)
     * @param  int  $weeklyWeeks  Number of weeks of weekly backups to keep (Father)
     * @param  int  $monthlyMonths  Number of months of monthly backups to keep (Grandfather)
     */
    public function __construct(
        protected int $dailyDays = 14,
        protected int $weeklyWeeks = 5,
        protected int $monthlyMonths = 12,
    ) {
        $this->dailyDays = max(1, $this->dailyDays);
        $this->weeklyWeeks = max(1, $this->weeklyWeeks);
        $this->monthlyMonths = max(1, $this->monthlyMonths);
    }

    public function getDailyDays(): int
    {
        return $this->dailyDays;
    }

    public function getWeeklyWeeks(): int
    {
        return $this->weeklyWeeks;
    }

    public function getMonthlyMonths(): int
    {
        return $this->monthlyMonths;
    }

    /**
     * Categorize an archive into daily (Son), weekly (Father), or monthly (Grandfather).
     */
    public function categorizeArchive(Carbon $date, ?Carbon $referenceDate = null): string
    {
        $now = $referenceDate ? $referenceDate->copy() : Carbon::now();
        $dailyCutoff = $now->copy()->subDays($this->dailyDays);
        $weeklyCutoff = $dailyCutoff->copy()->subWeeks($this->weeklyWeeks);

        if ($date->gte($dailyCutoff)) {
            return 'daily';
        }

        if ($date->gte($weeklyCutoff)) {
            return 'weekly';
        }

        return 'monthly';
    }

    /**
     * Extract the timestamp from an archive path or name.
     * Expected format: backup-{tier}-YYYY-MM-DD-HH-II-SS.zip
     */
    public static function parseArchiveDate(string $filename, ?int $fallbackTimestamp = null): Carbon
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2})/', basename($filename), $matches)) {
            try {
                return Carbon::createFromFormat('Y-m-d-H-i-s', $matches[1]);
            } catch (\Throwable) {
                // Fall back if parse fails
            }
        }

        if (is_int($fallbackTimestamp) && $fallbackTimestamp > 0) {
            return Carbon::createFromTimestamp($fallbackTimestamp);
        }

        return Carbon::now();
    }

    /**
     * Determine which archives to keep and which to delete under GFS retention.
     *
     * @param  Collection<int, array{path: string, name?: string, date: Carbon, size?: int|null}>  $archives
     * @param  Carbon|null  $referenceDate  Date to evaluate against (defaults to now)
     * @return array{keep: Collection, delete: Collection}
     */
    public function planPruning(Collection $archives, ?Carbon $referenceDate = null): array
    {
        if ($archives->isEmpty()) {
            return [
                'keep' => collect(),
                'delete' => collect(),
            ];
        }

        $now = $referenceDate ? $referenceDate->copy() : Carbon::now();

        // Separate and protect uploaded restore files (e.g. backup-daily-uploaded-*)
        $uploaded = $archives->filter(function (array $archive) {
            return Str::contains(basename($archive['path']), '-uploaded-');
        });

        $eligible = $archives->reject(function (array $archive) {
            return Str::contains(basename($archive['path']), '-uploaded-');
        });

        if ($eligible->isEmpty()) {
            return [
                'keep' => $uploaded->values(),
                'delete' => collect(),
            ];
        }

        // Sort descending by date (newest first)
        $sorted = $eligible->sortByDesc(fn (array $archive) => $archive['date']->timestamp)->values();

        // Rule: The most recent backup is always preserved
        $newest = $sorted->first();

        // Calculate GFS periods
        // Daily: [now - dailyDays, now]
        $dailyCutoff = $now->copy()->subDays($this->dailyDays);

        // Weekly: [now - dailyDays - weeklyWeeks, now - dailyDays]
        $weeklyCutoff = $dailyCutoff->copy()->subWeeks($this->weeklyWeeks);

        // Monthly: [weeklyCutoff - monthlyMonths, weeklyCutoff]
        $monthlyCutoff = $weeklyCutoff->copy()->subMonths($this->monthlyMonths);

        $keep = collect();
        $delete = collect();

        // Split into buckets
        $dailyBucket = collect();
        $weeklyBucket = collect();
        $monthlyBucket = collect();
        $expiredBucket = collect();

        foreach ($sorted as $archive) {
            $date = $archive['date'];

            if ($date->gte($dailyCutoff)) {
                $dailyBucket->push($archive);
            } elseif ($date->gte($weeklyCutoff)) {
                $weeklyBucket->push($archive);
            } elseif ($date->gte($monthlyCutoff)) {
                $monthlyBucket->push($archive);
            } else {
                $expiredBucket->push($archive);
            }
        }

        // Son (Daily): keep 1 per day (format: Y-m-d)
        $dailyGroups = $dailyBucket->groupBy(fn (array $a) => $a['date']->format('Y-m-d'));
        foreach ($dailyGroups as $group) {
            // Newest in the day
            $keep->push($group->first());
            foreach ($group->slice(1) as $extra) {
                $delete->push($extra);
            }
        }

        // Father (Weekly): keep 1 per ISO week (format: o-W)
        $weeklyGroups = $weeklyBucket->groupBy(fn (array $a) => $a['date']->format('o-W'));
        foreach ($weeklyGroups as $group) {
            $keep->push($group->first());
            foreach ($group->slice(1) as $extra) {
                $delete->push($extra);
            }
        }

        // Grandfather (Monthly): keep 1 per calendar month (format: Y-m)
        $monthlyGroups = $monthlyBucket->groupBy(fn (array $a) => $a['date']->format('Y-m'));
        foreach ($monthlyGroups as $group) {
            $keep->push($group->first());
            foreach ($group->slice(1) as $extra) {
                $delete->push($extra);
            }
        }

        // Expired beyond monthly cutoff
        foreach ($expiredBucket as $expired) {
            $delete->push($expired);
        }

        // Always ensure newest is in keep and never in delete
        if ($newest && ! $keep->contains('path', $newest['path'])) {
            $keep->push($newest);
            $delete = $delete->reject(fn (array $a) => $a['path'] === $newest['path']);
        }

        // If any item was accidentally in delete that is also in keep, remove from delete
        $keepPaths = $keep->pluck('path')->all();
        $delete = $delete->reject(fn (array $a) => in_array($a['path'], $keepPaths, true));

        // Re-include uploaded archives in keep
        $finalKeep = $keep->merge($uploaded)->values();
        $finalDelete = $delete->values();

        return [
            'keep' => $finalKeep,
            'delete' => $finalDelete,
        ];
    }
}

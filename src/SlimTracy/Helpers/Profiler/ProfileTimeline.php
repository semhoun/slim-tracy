<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Profiler;

/**
 * Computes the shared time range and memory samples for a set of profiles.
 *
 * @internal
 */
final class ProfileTimeline
{
    /**
     * @param list<Profile> $profiles
     *
     * @return array{meta_time_zero: float, meta_time_total: float, meta_memory_peak: int,
     *     meta_time_line: array<int, array{meta_time_line__memory_usage: int}>}
     */
    public static function fromProfiles(array $profiles): array
    {
        $timeZero = PHP_FLOAT_MAX;
        $timeEnd = 0.0;
        $memoryPeak = 0;
        $timeline = [];
        foreach ($profiles as $profile) {
            $meta = $profile->metadata();
            $timeZero = min($timeZero, $meta[Profiler::START_TIME]);
            $timeEnd = max($timeEnd, $meta[Profiler::FINISH_TIME]);
            $memoryPeak = max($memoryPeak, $meta[Profiler::START_MEMORY_USAGE], $meta[Profiler::FINISH_MEMORY_USAGE]);
            self::sample($timeline, $meta[Profiler::START_TIME], $meta[Profiler::START_MEMORY_USAGE]);
            self::sample($timeline, $meta[Profiler::FINISH_TIME], $meta[Profiler::FINISH_MEMORY_USAGE]);
        }

        $timeZero = min($timeZero, $timeEnd);
        ksort($timeline);
        return [
            ProfilerService::META_TIME_ZERO => $timeZero,
            ProfilerService::META_TIME_TOTAL => max($timeEnd - $timeZero, 0.001),
            ProfilerService::META_MEMORY_PEAK => $memoryPeak,
            ProfilerService::META_TIME_LINE => $timeline,
        ];
    }

    /**
     * @param array<int, array{meta_time_line__memory_usage: int}> $timeline
     */
    private static function sample(array &$timeline, float $time, int $memory): void
    {
        $timeline[(int) ($time * 1000)] = [ProfilerService::META_TIME_LINE__MEMORY_USAGE => $memory];
    }
}

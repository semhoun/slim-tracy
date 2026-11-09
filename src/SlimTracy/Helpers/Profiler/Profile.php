<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Profiler;

use JsonSerializable;

/**
 * Profile.
 *
 * @author   Petr Knap <dev@petrknap.cz>
 *
 * @since    2015-12-19
 *
 * @license  https://github.com/petrknap/php-profiler/blob/master/LICENSE MIT
 */
final class Profile implements JsonSerializable
{
    //region JSON keys
    private const ABSOLUTE_DURATION = 'absolute_duration';

    private const DURATION = 'duration';

    private const ABSOLUTE_MEMORY_USAGE_CHANGE = 'absolute_memory_usage_change';

    private const MEMORY_USAGE_CHANGE = 'memory_usage_change';

    /**
     * @param array<string, int|float|string|null> $meta Measurement labels, timestamps and offsets.
     *
     * Preserve the published constructor parameter names for named arguments.
     *
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        private array $meta,
        /**
         * Absolute duration in seconds.
         */
        private float $absoluteDuration = 0,
        /**
         * Duration in seconds.
         */
        private float $duration = 0,
        /**
         * Absolute memory usage change in bytes.
         */
        private int $absoluteMemoryUsageChange = 0,
        /**
         * Memory usage change in bytes.
         */
        private int $memoryUsageChange = 0
    ) {
    }

    /**
     * @return array<string, int|float|string|null>
     */
    public function metadata(): array
    {
        return $this->meta;
    }

    /** Return elapsed seconds including nested measurements. */
    public function absoluteDuration(): float
    {
        return $this->absoluteDuration;
    }

    /** Return elapsed seconds excluding nested measurements. */
    public function duration(): float
    {
        return $this->duration;
    }

    /** Return the memory delta in bytes including nested measurements. */
    public function absoluteMemoryUsageChange(): int
    {
        return $this->absoluteMemoryUsageChange;
    }

    /** Return the memory delta in bytes excluding nested measurements. */
    public function memoryUsageChange(): int
    {
        return $this->memoryUsageChange;
    }

    /** Record the finish sample and compute inclusive and exclusive totals. */
    public function complete(?string $label, float $now, int $memoryUsage): void
    {
        $this->meta[SimpleProfiler::FINISH_LABEL] = $label;
        $this->meta[SimpleProfiler::FINISH_TIME] = $now;
        $this->meta[SimpleProfiler::FINISH_MEMORY_USAGE] = $memoryUsage;
        $this->absoluteDuration = $now - $this->meta[SimpleProfiler::START_TIME];
        $this->duration = $this->absoluteDuration - $this->meta[SimpleProfiler::TIME_OFFSET];
        $this->absoluteMemoryUsageChange = $memoryUsage - $this->meta[SimpleProfiler::START_MEMORY_USAGE];
        $this->memoryUsageChange = $this->absoluteMemoryUsageChange - $this->meta['memory_usage_offset'];
    }

    /** Exclude a completed child's time and memory from this profile's own totals. */
    public function excludeChild(self $child): void
    {
        $this->meta[SimpleProfiler::TIME_OFFSET] += $child->absoluteDuration;
        $this->meta['memory_usage_offset'] += $child->absoluteMemoryUsageChange;
    }

    /** Position this measurement as percentages of the shared timeline. */
    public function positionWithin(float $start, float $duration): void
    {
        $before = floor(($this->meta[SimpleProfiler::START_TIME] - $start) / $duration * 100);
        $active = floor($this->duration / $duration * 100);
        $inactive = floor(($this->absoluteDuration - $this->duration) / $duration * 100);
        $this->meta[ProfilerService::TIME_LINE_BEFORE] = $before;
        $this->meta[ProfilerService::TIME_LINE_ACTIVE] = $active;
        $this->meta[ProfilerService::TIME_LINE_INACTIVE] = $inactive;
        $this->meta[ProfilerService::TIME_LINE_AFTER] = 100 - $before - $active - $inactive;
    }

    /**
     * @return array<string, int|float|string|null>
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            $this->meta,
            [
                self::ABSOLUTE_DURATION => $this->absoluteDuration,
                self::DURATION => $this->duration,
                self::ABSOLUTE_MEMORY_USAGE_CHANGE => $this->absoluteMemoryUsageChange,
                self::MEMORY_USAGE_CHANGE => $this->memoryUsageChange,
            ]
        );
    }
}

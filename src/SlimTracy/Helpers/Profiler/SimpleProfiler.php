<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Profiler;

use SlimTracy\Helpers\Profiler\Exception\EmptyStackException;
use SlimTracy\Helpers\Profiler\Exception\ProfilerException;

/**
 * Simple PHP class for profiling.
 *
 * @author   Petr Knap <dev@petrknap.cz>
 *
 * @since    2015-12-13
 *
 * @license  https://github.com/petrknap/php-profiler/blob/master/LICENSE MIT
 */
class SimpleProfiler
{
    //region Meta keys
    public const START_LABEL = 'start_label';

     // string
    public const START_TIME = 'start_time';

     // float start time in seconds
    public const START_MEMORY_USAGE = 'start_memory_usage';

     // int amount of used memory at start in bytes
    public const FINISH_LABEL = 'finish_label';

     // string
    public const FINISH_TIME = 'finish_time';

     // float finish time in seconds
    public const FINISH_MEMORY_USAGE = 'finish_memory_usage';

     // int amount of used memory at finish in bytes
    public const TIME_OFFSET = 'time_offset';

     // float time offset in seconds
    protected const MEMORY_USAGE_OFFSET = 'memory_usage_offset'; // int amount of memory usage offset in bytes
    //endregion

    protected static bool $enabled = false;

    /**
     * @var array<Profile>
     */
    protected static array $stack = [];

    /**
     * memory_get_usage.
     */
    protected static bool $realUsage = false;

    /**
     * Enable profiler.
     */
    public static function enable(mixed $realUsage = false): void
    {
        static::$enabled = true;
        static::$realUsage = (bool) $realUsage;
    }

    /**
     * Disable profiler.
     */
    public static function disable(): void
    {
        static::$enabled = false;
    }

    /**
     * @return bool true if profiler is enabled, otherwise false
     */
    public static function isEnabled(): bool
    {
        return static::$enabled;
    }

    /**
     * @return bool true if use realUsage memory, , otherwise false
     */
    public static function isMemRealUsage(): bool
    {
        return static::$realUsage;
    }

    /**
     * Start profiling.
     *
     * @param mixed  $args          [optional]
     *
     * @return bool true on success or false on failure
     */
    public static function start(?string $labelOrFormat = null, mixed $args = null): bool
    {
        if (static::$enabled) {
            if ($args === null) {
                $label = $labelOrFormat;
            } else {
                /** @noinspection SpellCheckingInspection */
                $label = call_user_func_array(sprintf(...), func_get_args());
            }

            $now = microtime(true);

            $memoryUsage = static::$realUsage ? memory_get_usage(true) : memory_get_usage();

            $profile = new Profile();
            $profile->meta = [
                self::START_LABEL => $label,
                self::TIME_OFFSET => 0,
                self::MEMORY_USAGE_OFFSET => 0,
                self::START_TIME => $now,
                self::START_MEMORY_USAGE => $memoryUsage,
            ];

            static::$stack[] = $profile;

            return true;
        }

        return false;
    }

    /**
     * Finish profiling and get result.
     *
     * @param mixed  $args          [optional]
     *
     * @throws ProfilerException
     *
     * @return bool|Profile profile on success or false on failure
     */
    public static function finish(?string $labelOrFormat = null, mixed $args = null): bool|Profile
    {
        if (static::$enabled) {
            $now = microtime(true);

            $memoryUsage = static::$realUsage ? memory_get_usage(true) : memory_get_usage();

            if (static::$stack === []) {
                throw new EmptyStackException('The stack is empty. Call ' . static::class . '::start() first.');
            }

            if ($args === null) {
                $label = $labelOrFormat;
            } else {
                /** @noinspection SpellCheckingInspection */
                $label = call_user_func_array(sprintf(...), func_get_args());
            }

            /** @var Profile $profile */
            $profile = array_pop(static::$stack);
            $profile->meta[self::FINISH_LABEL] = $label;
            $profile->meta[self::FINISH_TIME] = $now;
            $profile->meta[self::FINISH_MEMORY_USAGE] = $memoryUsage;
            $profile->absoluteDuration = $profile->meta[self::FINISH_TIME] - $profile->meta[self::START_TIME];
            $profile->duration = $profile->absoluteDuration - $profile->meta[self::TIME_OFFSET];
            $profile->absoluteMemoryUsageChange = $profile->meta[self::FINISH_MEMORY_USAGE] -
                $profile->meta[self::START_MEMORY_USAGE];
            $profile->memoryUsageChange = $profile->absoluteMemoryUsageChange -
                $profile->meta[self::MEMORY_USAGE_OFFSET];

            if (static::$stack !== []) {
                $timeOffset = &static::$stack[count(static::$stack) - 1]->meta[self::TIME_OFFSET];
                $timeOffset += $profile->absoluteDuration;

                $memoryUsageOffset = &static::$stack[count(static::$stack) - 1]->meta[self::MEMORY_USAGE_OFFSET];
                $memoryUsageOffset += $profile->absoluteMemoryUsageChange;
            }

            return $profile;
        }

        return false;
    }
}

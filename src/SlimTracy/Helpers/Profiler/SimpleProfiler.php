<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Profiler;

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
abstract class SimpleProfiler
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

    protected static ProfilingSession $session;

    /**
     * memory_get_usage.
     */
    protected static bool $realUsage = false;

    /**
     * Enable profiler.
     */
    public static function enable(bool $realUsage = false): void
    {
        static::$session = new ProfilingSession();
        static::$enabled = true;
        static::$realUsage = $realUsage;
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
     * Additional arguments are forwarded unchanged to sprintf when present.
     *
     * @return bool true on success or false on failure
     */
    public static function start(?string $labelOrFormat = null, mixed ...$args): bool
    {
        if (static::$enabled) {
            $now = microtime(true);

            $memoryUsage = memory_get_usage(static::$realUsage);

            $profile = new Profile([
                self::START_LABEL => self::formatLabel($labelOrFormat, ...$args),
                self::TIME_OFFSET => 0,
                self::MEMORY_USAGE_OFFSET => 0,
                self::START_TIME => $now,
                self::START_MEMORY_USAGE => $memoryUsage,
            ]);

            static::$session->start($profile);

            return true;
        }

        return false;
    }

    /**
     * Finish profiling and get result.
     *
     * Additional arguments are forwarded unchanged to sprintf when present.
     *
     * @throws ProfilerException
     *
     * @return bool|Profile profile on success or false on failure
     */
    public static function finish(?string $labelOrFormat = null, mixed ...$args): false|Profile
    {
        if (static::$enabled) {
            $now = microtime(true);

            $memoryUsage = memory_get_usage(static::$realUsage);

            return static::$session->finish(self::formatLabel($labelOrFormat, ...$args), $now, $memoryUsage);
        }

        return false;
    }

    /**
     * Values are passed directly to sprintf, including user objects and resources.
     */
    private static function formatLabel(?string $label, mixed ...$args): ?string
    {
        return $args === [] ? $label : sprintf($label, ...$args);
    }
}

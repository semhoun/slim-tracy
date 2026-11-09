<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Profiler;

/**
 * Advanced PHP class for profiling.
 *
 * @author   Petr Knap <dev@petrknap.cz>
 *
 * @since    2015-12-19
 *
 * @license  https://github.com/petrknap/php-profiler/blob/master/LICENSE MIT
 */
abstract class AdvancedProfiler extends SimpleProfiler
{
    protected static bool $enabled = false;

    protected static ProfilingSession $session;

    /**
     * Enable a profiling session with an optional result processor.
     *
     * Post processor is callable with one input argument (return from finish method)
     * and is called at the end of finish method.
     */
    public static function enable(bool $realUsage = false, ?callable $postProcessor = null): void
    {
        parent::enable($realUsage);
        static::$session = new ProfilingSession($postProcessor);
    }

    /**
     * Get current "{file}#{line}".
     *
     * The first argument selects the backtrace depth (defaults to zero).
     *
     * @return bool|string current "{file}#{line}" on success or false on failure
     */
    public static function getCurrentFileHashLine(int $depth = 0): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $backtrace = $backtrace[$depth];
        return sprintf(
            '%s#%s',
            $backtrace['file'],
            $backtrace['line']
        );
    }

    public static function start(?string $labelOrFormat = null, mixed ...$args): bool
    {
        return parent::start($labelOrFormat ?? static::getCurrentFileHashLine(1), ...$args);
    }

    public static function finish(?string $labelOrFormat = null, mixed ...$args): Profile|false
    {
        return parent::finish($labelOrFormat ?? static::getCurrentFileHashLine(1), ...$args);
    }
}

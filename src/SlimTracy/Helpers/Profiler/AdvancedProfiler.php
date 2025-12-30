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
class AdvancedProfiler extends SimpleProfiler
{
    protected static bool $enabled = false;

    /**
     * @var array<Profile>
     */
    protected static array $stack = [];

    /**
     * @var callable
     */
    protected static $postProcessor;

    /**
     * Set post processor.
     *
     * Post processor is callable with one input argument (return from finish method)
     * and is called at the end of finish method.
     */
    public static function setPostProcessor(callable $postProcessor): void
    {
        static::$postProcessor = $postProcessor;
    }

    /**
     * Get current "{file}#{line}".
     *
     * @return bool|string current "{file}#{line}" on success or false on failure
     */
    public static function getCurrentFileHashLine(...$args): bool|string
    {
        $deep = &$args[0];

        $backtrace = debug_backtrace();
        $backtrace = &$backtrace[$deep ?: 0];
        return sprintf(
            '%s#%s',
            $backtrace['file'],
            $backtrace['line']
        );
    }

    public static function start(?string $labelOrFormat = null, mixed $args = null): bool
    {
        if (static::$enabled) {
            if ($labelOrFormat === null) {
                $labelOrFormat = static::getCurrentFileHashLine(1);
                $args = null;
            }

            return parent::start($labelOrFormat, $args);
        }

        return false;
    }

    public static function finish(?string $labelOrFormat = null, mixed $args = null): Profile|bool
    {
        if (static::$enabled) {
            if ($labelOrFormat === null) {
                $labelOrFormat = static::getCurrentFileHashLine(1);
                $args = null;
            }

            $profile = parent::finish($labelOrFormat, $args);

            if (static::$postProcessor === null) {
                return $profile;
            }

            return call_user_func(static::$postProcessor, $profile);
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Profiler;

/** Manual profiling facade that sends completed measurements to the shared collector. */
final class Profiler extends AdvancedProfiler
{
    /** Whether this concrete profiler currently accepts measurements. */
    protected static bool $enabled = false;

    /** Session whose completed measurements feed the profiling panel. */
    protected static ProfilingSession $session;

    /** Enable profiling with the shared collector unless a custom processor is supplied. */
    public static function enable(bool $realUsage = false, ?callable $postProcessor = null): void
    {
        parent::enable($realUsage, $postProcessor ?? ProfilerService::getInstance()->addProfile(...));
    }
}

<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Profiler;

final class Profiler extends AdvancedProfiler
{
    protected static bool $enabled = false;

    protected static ProfilingSession $session;

    public static function enable(bool $realUsage = false, ?callable $postProcessor = null): void
    {
        parent::enable($realUsage, $postProcessor ?? ProfilerService::getInstance()->addProfile(...));
    }
}

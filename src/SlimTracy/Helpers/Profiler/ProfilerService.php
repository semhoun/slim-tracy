<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Profiler;

/**
 * @internal
 */
final class ProfilerService
{
    public const META_TIME_ZERO = 'meta_time_zero';

    public const META_TIME_TOTAL = 'meta_time_total';

    public const META_MEMORY_PEAK = 'meta_memory_peak';

    public const META_TIME_LINE = 'meta_time_line';

    public const META_TIME_LINE__MEMORY_USAGE = 'meta_time_line__memory_usage';

    public const TIME_LINE_BEFORE = 'time_line_before';

    // int [0 - 100] percentage
    public const TIME_LINE_ACTIVE = 'time_line_active';

    // int [0 - 100] percentage
    public const TIME_LINE_INACTIVE = 'time_line_inactive';

     // int [0 - 100] percentage
    public const TIME_LINE_AFTER = 'time_line_after'; // int [0 - 100] percentage

    private static ?self $instance = null;

    /**
     * @var array<Profile>
     */
    private array $profiles = [];

    /** Keep the shared collector accessible only through its lazy accessor. */
    private function __construct()
    {
    }

    /** Return the request's shared manual-profile collector, creating it lazily. */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** Check for an existing collector without initializing one. */
    public static function hasInstance(): bool
    {
        return self::$instance instanceof self;
    }

    /**
     * Initializes the service.
     */
    public static function init(): void
    {
        self::getInstance();
    }

    /**
     * @internal
     */
    public function addProfile(Profile $profile): Profile
    {
        $this->profiles[] = $profile;

        return $profile;
    }

    /**
     * @return array<Profile>
     */
    public function getProfiles(): array
    {
        return $this->profiles;
    }

    /** Visit measurements with their positions within the common timeline. */
    public function iterateProfiles(callable $callback): void
    {
        $metaData = $this->getMetaData();
        foreach ($this->profiles as $profile) {
            $profile->positionWithin($metaData[self::META_TIME_ZERO], $metaData[self::META_TIME_TOTAL]);
            $callback($profile, $metaData);
        }
    }

    /** Visit memory samples as relative time and peak-memory percentages. */
    public function iterateMemoryTimeLine(callable $callback): void
    {
        $metaData = $this->getMetaData();
        $totalTime = 0;
        $height = 0;
        foreach ($metaData[self::META_TIME_LINE] as $time => $values) {
            $time = $time / 1000 - $metaData[self::META_TIME_ZERO];
            $height = floor(
                $values[self::META_TIME_LINE__MEMORY_USAGE] / $metaData[self::META_MEMORY_PEAK] * 100
            );
            $totalTime += $time;
            call_user_func($callback, $time, $height, $metaData);
        }

        if ($totalTime < $metaData[self::META_TIME_TOTAL]) {
            call_user_func($callback, $metaData[self::META_TIME_TOTAL] - $totalTime, $height, $metaData);
        }
    }

    /**
     * @return array<string, float|int|array<int, array<string, int>>>
     *
     * The timeline builder is a stateless calculation over these profiles.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function getMetaData(): array
    {
        return ProfileTimeline::fromProfiles($this->profiles);
    }
}

<?php

namespace Tests\SlimTracy\Helpers\Profiler;

use Tests\BaseTestCase;
use SlimTracy\Helpers\Profiler\Profiler;
use SlimTracy\Helpers\Profiler\ProfilerService;
use SlimTracy\Helpers\Profiler\AdvancedProfiler;
use SlimTracy\Helpers\Profiler\SimpleProfiler;
use SlimTracy\Helpers\Profiler\Profile;
use SlimTracy\Helpers\Profiler\ProfilingSession;
use SlimTracy\Helpers\Profiler\Exception\EmptyStack;

/**
 * Class ProfilerTest
 * @package Tests\SlimTracy\Helpers\Profiler
 */
class ProfilerTest extends BaseTestCase
{
    /** Verify enabling the profiler initializes its service and records a measurement. */
    public function testEnableCallsProfilerServiceInit()
    {
        Profiler::enable();

        Profiler::start();
        Profiler::finish();

        /** @noinspection PhpInternalEntityUsedInspection */
        $this->assertCount(1, ProfilerService::getInstance()->getProfiles());
        /** @noinspection PhpInternalEntityUsedInspection */
        $this->assertTrue(ProfilerService::hasInstance());
    }

    /** Verify the default panel explains that profiling is disabled. */
    public function testProfilerPanel()
    {
        $panel = new \SlimTracy\Helpers\ProfilerPanel($this->cfg['settings']['tracy']['configs']['ProfilerPanel']);
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);

        // test Tracy tab
        $this->assertMatchesRegularExpression('#Profiler info#', $panel->getTab());
        // without SlimTracy config 'Profiling is disabled' by default
        $this->assertMatchesRegularExpression('#Profiling is disabled#', $panel->getPanel());
    }

    /** Verify nested measurements subtract child costs and notify the processor. */
    public function testNestedMeasurementsExcludeChildAndInvokeProcessor(): void
    {
        $completed = [];
        $session = new ProfilingSession(static function (Profile $profile) use (&$completed): Profile {
            $completed[] = $profile;
            return $profile;
        });
        $parent = new Profile([
            'start_label' => 'parent', 'start_time' => 10.0, 'start_memory_usage' => 100,
            'time_offset' => 0, 'memory_usage_offset' => 0,
        ]);
        $child = new Profile([
            'start_label' => 'child', 'start_time' => 11.0, 'start_memory_usage' => 130,
            'time_offset' => 0, 'memory_usage_offset' => 0,
        ]);
        $session->start($parent);
        $session->start($child);
        $this->assertSame($child, $session->finish('child end', 12.0, 160));
        $this->assertSame($parent, $session->finish('parent end', 14.0, 200));
        $this->assertSame([$child, $parent], $completed);
        $this->assertSame(4.0, $parent->absoluteDuration());
        $this->assertSame(3.0, $parent->duration());
        $this->assertSame(100, $parent->absoluteMemoryUsageChange());
        $this->assertSame(70, $parent->memoryUsageChange());
        $this->assertSame(1.0, $child->duration());
        $this->assertSame(30, $child->memoryUsageChange());
        $this->assertSame('parent end', $parent->metadata()['finish_label']);
    }

    /** Verify finishing an empty session reports stack underflow. */
    public function testEmptySessionReportsUnderflow(): void
    {
        $this->expectException(EmptyStack::class);
        (new ProfilingSession())->finish(null, 1.0, 0);
    }

    /**
     * Exercise the public static profiler facades while disabled.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testDisabledProfilerDoesNotProcessMeasurements(): void
    {
        $this->assertFalse(Profiler::start('disabled'));
        $this->assertFalse(Profiler::finish('disabled'));
        $this->assertFalse(AdvancedProfiler::start('disabled'));
        $this->assertFalse(AdvancedProfiler::finish('disabled'));
    }

    /**
     * Exercise the static formatting facade with a resource argument.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testFormattingPreservesAllArgumentsIncludingResources(): void
    {
        $resource = fopen('php://memory', 'r+');
        try {
            SimpleProfiler::enable();
            SimpleProfiler::start('%s:%d:%s', 'start', 42, $resource);
            $profile = SimpleProfiler::finish('%s:%d:%s', 'finish', 43, $resource);
            $this->assertSame(sprintf('%s:%d:%s', 'start', 42, $resource), $profile->metadata()['start_label']);
            $this->assertSame(sprintf('%s:%d:%s', 'finish', 43, $resource), $profile->metadata()['finish_label']);
        } finally {
            fclose($resource);
        }
    }

    /**
     * Exercise the static advanced-profiler API and its service accessor.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testProcessorInjectionAndAutomaticLabels(): void
    {
        $processed = [];
        AdvancedProfiler::enable(false, static function (Profile $profile) use (&$processed): Profile {
            $processed[] = $profile;
            return $profile;
        });
        AdvancedProfiler::start();
        $profile = AdvancedProfiler::finish();
        $this->assertSame([$profile], $processed);
        $this->assertStringStartsWith(__FILE__ . '#', $profile->metadata()['start_label']);
        $this->assertStringStartsWith(__FILE__ . '#', $profile->metadata()['finish_label']);
        $this->assertCount(0, ProfilerService::getInstance()->getProfiles());
    }
}

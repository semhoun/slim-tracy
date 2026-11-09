<?php

/**
 * Copyright 2016 1f7.wizard@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Tests\SlimTracy\Helpers;

use Tests\BaseTestCase;
use SlimTracy\Helpers\Profiler\Profile;
use SlimTracy\Helpers\Profiler\Profiler;
use SlimTracy\Helpers\Profiler\ProfilerService;

/**
 * Class ProfilerPanelTest
 * @package Tests\SlimTracy\Helpers
 */
class ProfilerPanelTest extends BaseTestCase
{
    public function testProfilerPanelDisabled()
    {
        $this->assertFalse(Profiler::isEnabled());

        $panel = new \SlimTracy\Helpers\ProfilerPanel($this->cfg['settings']['tracy']['configs']['ProfilerPanel']);
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);

        // test Tracy tab
        $this->assertMatchesRegularExpression('#Profiler info#', $panel->getTab());
        // test Tracy panel
        $this->assertMatchesRegularExpression('#Profiling is disabled#s', $panel->getPanel());
    }

    public function testProfilerPanelEnabledShowMemEffective()
    {
        $this->assertFalse(Profiler::isEnabled());
        Profiler::enable();//effective
        $this->assertTrue(Profiler::isEnabled());
        Profiler::disable();//test disable
        $this->assertFalse(Profiler::isEnabled());
        Profiler::enable();//effective

        $panel = new \SlimTracy\Helpers\ProfilerPanel($this->cfg['settings']['tracy']['configs']['ProfilerPanel']);
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);

        // test Tracy tab
        $this->assertMatchesRegularExpression('#Profiler info#s', $panel->getTab());
        // test Tracy panel
        $this->assertMatchesRegularExpression('#Memory change#s', $panel->getPanel());

        $this->assertMatchesRegularExpression('#effective#s', $panel->getPanel());
    }

    public function testProfilerPanelEnabledShowMemAbsolute()
    {
        $this->assertFalse(Profiler::isEnabled());
        Profiler::enable(true);//absolute
        $this->assertTrue(Profiler::isEnabled());
        Profiler::disable();//test disable
        $this->assertFalse(Profiler::isEnabled());
        Profiler::enable(true);//effective

        $panel = new \SlimTracy\Helpers\ProfilerPanel($this->cfg['settings']['tracy']['configs']['ProfilerPanel']);
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);

        // test Tracy tab
        $this->assertMatchesRegularExpression('#Profiler info#s', $panel->getTab());
        // test Tracy panel
        $this->assertMatchesRegularExpression('#Memory change#s', $panel->getPanel());

        $this->assertMatchesRegularExpression('#absolute#s', $panel->getPanel());
    }

    public function testProfilerService()
    {
        Profiler::enable();

        $p = ProfilerService::getInstance();
        $this->fillTestData($p);
        $this->assertCount(2, $p->getProfiles());

        $panel = new \SlimTracy\Helpers\ProfilerPanel($this->cfg['settings']['tracy']['configs']['ProfilerPanel']);
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);

        // test Tracy tab
        $this->assertMatchesRegularExpression('#2 profiles#s', $panel->getTab());
        // check svg image exists
        $this->assertMatchesRegularExpression('#svg style=#s', $panel->getPanel());
    }

    public function testRenderingOptionsKeepMeasurementAndTimelineFormats(): void
    {
        Profiler::enable();
        $service = ProfilerService::getInstance();
        $this->fillTestData($service);
        foreach (['effective', 'absolute'] as $value) {
            foreach ([false, true] as $chart) {
                foreach ([false, true] as $short) {
                    foreach ([false, true] as $timeline) {
                        $panel = new \SlimTracy\Helpers\ProfilerPanel([
                            'primaryValue' => $value,
                            'show' => ['memoryUsageChart' => $chart, 'shortProfiles' => $short, 'timeLines' => $timeline],
                        ]);
                        $html = $panel->getPanel();
                        $this->assertStringContainsString('Time (' . $value . ')', $html);
                        $this->assertStringContainsString('<td colspan="2">initRoutes</td>', $html);
                        $this->assertStringContainsString('<td>13&nbsp;ms (13&nbsp;ms)</td>', $html);
                        $this->assertSame($chart, str_contains($html, '<svg style='));
                        $this->assertSame($timeline, str_contains($html, 'class="tracy-addons-profiler-bar"'));
                    }
                }
            }
        }
        $profile = $service->getProfiles()[0];
        $this->assertSame(0.0, $profile->metadata()['time_line_before']);
        $this->assertSame(100.0, array_sum(array_intersect_key($profile->metadata(), array_flip([
            'time_line_before', 'time_line_active', 'time_line_inactive', 'time_line_after',
        ]))));
    }

    public function testDistinctLabelsAndShortProfileFiltering(): void
    {
        Profiler::enable();
        $service = ProfilerService::getInstance();
        $service->addProfile(new Profile([
            'start_label' => 'start', 'finish_label' => 'finish',
            'start_time' => 1.0, 'finish_time' => 2.0,
            'start_memory_usage' => 1000, 'finish_memory_usage' => 2024,
        ], 1.0, 0.75, 1024, 512));
        $service->addProfile(new Profile([
            'start_label' => 'tiny', 'finish_label' => 'tiny',
            'start_time' => 1.0, 'finish_time' => 1.00001,
            'start_memory_usage' => 1000, 'finish_memory_usage' => 1000,
        ], 0.00001, 0.00001));
        $effective = new \SlimTracy\Helpers\ProfilerPanel(['show' => ['shortProfiles' => false]]);
        $html = $effective->getPanel();
        $this->assertStringContainsString('<td>start</td><td>finish</td>', $html);
        $this->assertStringContainsString('<td>1000&nbsp;ms (750&nbsp;ms)</td>', $html);
        $this->assertStringContainsString('<td>1&nbsp;kB (0&nbsp;kB)</td>', $html);
        $this->assertStringNotContainsString('>tiny</td>', $html);
        $absolute = new \SlimTracy\Helpers\ProfilerPanel(['primaryValue' => 'absolute']);
        $html = $absolute->getPanel();
        $this->assertStringContainsString('<td>750&nbsp;ms (1000&nbsp;ms)</td>', $html);
        $this->assertStringContainsString('<td>0&nbsp;kB (1&nbsp;kB)</td>', $html);
        $this->assertStringContainsString('>tiny</td>', $html);
    }

    protected function fillTestData(&$p)
    {
        $testProfiles =  [
            0 =>
                [
                    'meta' =>
                         [
                            'start_label' => 'initRoutes',
                            'time_offset' => 0,
                            'memory_usage_offset' => 0,
                            'start_time' => 1482687818.8077681,
                            'start_memory_usage' => 1061384,
                            'finish_label' => 'initRoutes',
                            'finish_time' => 1482687818.821182,
                            'finish_memory_usage' => 1075296,
                         ],
                         'absoluteDuration' => 0.013413906097412109,
                         'duration' => 0.013413906097412109,
                         'absoluteMemoryUsageChange' => 13912,
                         'memoryUsageChange' => 13912,
                ],
                1 =>
                [
                    'meta' =>
                         [
                            'start_label' => 'initModules',
                            'time_offset' => 0,
                            'memory_usage_offset' => 0,
                            'start_time' => 1482687818.821209,
                            'start_memory_usage' => 1075696,
                            'finish_label' => 'initModules',
                            'finish_time' => 1482687818.9348431,
                            'finish_memory_usage' => 1490584,
                         ],
                         'absoluteDuration' => 0.11363410949707031,
                         'duration' => 0.11363410949707031,
                         'absoluteMemoryUsageChange' => 414888,
                         'memoryUsageChange' => 414888,
                ],
        ];

        foreach ($testProfiles as $profile) {
            $np = new Profile(
                $profile['meta'],
                $profile['absoluteDuration'],
                $profile['duration'],
                $profile['absoluteMemoryUsageChange'],
                $profile['memoryUsageChange']
            );
            $p->addProfile($np);
        }
    }
}

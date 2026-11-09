<?php

/**
 * Copyright 2016 1f7.wizard@gmail.com.
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

declare(strict_types=1);

namespace SlimTracy\Helpers;

use SlimTracy\Helpers\Profiler\Profile;
use SlimTracy\Helpers\Profiler\Profiler;
use SlimTracy\Helpers\Profiler\ProfilerService;
use SlimTracy\Helpers\Profiler\SimpleProfiler;
use Tracy\IBarPanel;

/** Displays manually collected timing and memory profiles. */
final readonly class ProfilerPanel implements IBarPanel
{
    /**
     * @var ProfilerService Shared collection of manually recorded profiles.
     */
    private ProfilerService $profilerService;

    /**
     * @var array{primaryValue: string, show: array<string, bool>} Resolved chart and memory display settings.
     */
    private array $config;

    /**
     * Merges display overrides with the profiler defaults.
     *
     * ProfilerService's singleton accessor supplies the same shared collection used by the static profiler facade.
     *
     * @param array{primaryValue?: string, show?: array<string, bool>} $config Chart and memory display overrides.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(array $config = [])
    {
        $this->config = array_replace_recursive(self::getDefaultConfig(), $config);
        $this->profilerService = ProfilerService::getInstance();
    }

    /**
     * Builds the default chart and memory display settings.
     *
     * The profiler's public static facade owns the shared memory measurement mode.
     *
     * @internal
     *
     * @return array{primaryValue: string, show: array<string, bool>} Defaults based on the memory measurement mode.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public static function getDefaultConfig(): array
    {
        return [
            'primaryValue' => SimpleProfiler::isMemRealUsage() ? 'absolute' : 'effective',
            'show' => ['memoryUsageChart' => true, 'shortProfiles' => true, 'timeLines' => true],
        ];
    }

    /**
     * Summarizes the number of manually recorded profiles.
     *
     * The profiler's public static facade owns the enabled state, not the profile collection service.
     *
     * @return string Toolbar HTML with the profile count or disabled state.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getTab(): string
    {
        $count = count($this->profilerService->getProfiles());
        return sprintf(
            '<span title="Profiler info">⏱ %s</span>',
            Profiler::isEnabled() ? sprintf($count === 1 ? '%d profile' : '%d profiles', $count) : 'disabled'
        );
    }

    /**
     * Renders the profile charts and detail sections.
     *
     * @return string Profiler template HTML using the resolved display settings.
     */
    public function getPanel(): string
    {
        $render = require __DIR__ . '/../Templates/profiler.phtml';
        return $render($this->config, $this->profiles(), $this->profilerService);
    }

    /**
     * Collects profiles in the service's iteration order.
     *
     * @return list<Profile> Recorded profiles in display order.
     */
    private function profiles(): array
    {
        $profiles = [];
        $this->profilerService->iterateProfiles(static function (Profile $profile) use (&$profiles): void {
            $profiles[] = $profile;
        });
        return $profiles;
    }
}

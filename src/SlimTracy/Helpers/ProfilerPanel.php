<?php

declare(strict_types=1);

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

namespace SlimTracy\Helpers;

use SlimTracy\Helpers\Profiler\Profile;
use SlimTracy\Helpers\Profiler\Profiler;
use SlimTracy\Helpers\Profiler\ProfilerService;
use SlimTracy\Helpers\Profiler\SimpleProfiler;
use Tracy\IBarPanel;

final readonly class ProfilerPanel implements IBarPanel
{
    private ProfilerService $profilerService;

    /**
     * @var array{primaryValue: string, show: array<string, bool>}
     */
    private array $config;

    /**
     * @param array{primaryValue?: string, show?: array<string, bool>} $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_replace_recursive(self::getDefaultConfig(), $config);
        $this->profilerService = ProfilerService::getInstance();
    }

    /**
     * @internal
     *
     * @return array{primaryValue: string, show: array<string, bool>}
     */
    public static function getDefaultConfig(): array
    {
        return [
            'primaryValue' => SimpleProfiler::isMemRealUsage() ? 'absolute' : 'effective',
            'show' => ['memoryUsageChart' => true, 'shortProfiles' => true, 'timeLines' => true],
        ];
    }

    public function getTab(): string
    {
        $count = count($this->profilerService->getProfiles());
        return sprintf(
            '<span title="Profiler info">⏱ %s</span>',
            Profiler::isEnabled() ? sprintf($count === 1 ? '%d profile' : '%d profiles', $count) : 'disabled'
        );
    }

    public function getPanel(): string
    {
        $render = require __DIR__ . '/../Templates/profiler.phtml';
        return $render($this->config, $this->profiles(), $this->profilerService);
    }

    /**
     * @return list<Profile>
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

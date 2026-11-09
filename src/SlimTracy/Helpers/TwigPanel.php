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

use Tracy\IBarPanel;

final class TwigPanel implements IBarPanel
{
    private readonly \Twig\Profiler\Dumper\HtmlDumper $htmlDumper;

    private ?string $icon = null;

    /**
     * @param array<string, string> $ver
     */
    public function __construct(private readonly \Twig\Profiler\Profile $data, private array $ver = [])
    {
        $this->htmlDumper = new \Twig\Profiler\Dumper\HtmlDumper();
    }

    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/TwigPanel.html');

        return '
        <span title="Twig Info">' . $this->icon .
            '<span class="tracy-label">' . sprintf('%.2f ms', $this->data->getDuration() * 1000) . '</span>
        </span>';
    }

    public function getPanel(): string
    {
        return '<h1>' . $this->icon . ' Slim ' . $this->ver['slim'] . ' / Twig </h1>
        <div class="tracy-inner">
            <p>
                <table width="100%">
                    <thead><tr><th><b>Twig profiler result</b></th></tr></thead>
                    <tr class="yes"><th><b>' . $this->htmlDumper->dump($this->data) . '</b></th></tr>
                </table>
            </p>
        </div>';
    }
}

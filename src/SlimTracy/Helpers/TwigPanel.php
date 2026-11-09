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

use Tracy\IBarPanel;
use Twig\Profiler\Dumper\HtmlDumper;

/** Displays Twig template profiling data and total rendering time. */
final class TwigPanel implements IBarPanel
{
    /**
     * @var \Twig\Profiler\Dumper\HtmlDumper Renderer for the collected Twig profile tree.
     */
    private readonly HtmlDumper $htmlDumper;

    /**
     * @var string|null Twig icon loaded while rendering the tab.
     */
    private ?string $icon = null;

    /**
     * Configures the collected Twig profile and framework label.
     *
     * @param \Twig\Profiler\Profile $data Collected template profile tree.
     * @param array<string, string>  $ver  Framework version labels.
     */
    public function __construct(private readonly \Twig\Profiler\Profile $data, private array $ver = [])
    {
        $this->htmlDumper = new HtmlDumper();
    }

    /**
     * Summarizes the total template rendering time.
     *
     * @return string Toolbar HTML with the Twig duration in milliseconds.
     */
    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/TwigPanel.html');

        return '
        <span title="Twig Info">' . $this->icon .
            '<span class="tracy-label">' . sprintf('%.2f ms', $this->data->getDuration() * 1000) . '</span>
        </span>';
    }

    /**
     * Renders the collected Twig profile tree.
     *
     * @return string Twig profiler HTML with the Slim version label.
     */
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

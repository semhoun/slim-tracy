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

/** Displays the host application's pre-rendered HTTP request dump. */
final class SlimRequestPanel implements IBarPanel
{
    /**
     * @var string|null Request icon loaded while rendering the tab.
     */
    private ?string $icon = null;

    /**
     * Configures the request dump and framework label.
     *
     * @param string|null           $content Pre-rendered request dump HTML.
     * @param array<string, string> $ver     Framework version labels.
     */
    public function __construct(private readonly ?string $content = null, private array $ver = [])
    {
    }

    /**
     * Renders the request toolbar tab.
     *
     * @return string Toolbar HTML identifying the request panel.
     */
    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/SlimRequestPanel.html');

        return '<span title="Slim Http Request">' . $this->icon . '</span>';
    }

    /**
     * Wraps the request dump in a scrollable panel.
     *
     * @return string Request panel HTML with the Slim version label.
     */
    public function getPanel(): string
    {
        return '<h1>' . $this->icon . ' Slim ' . $this->ver['slim'] . ' Request:</h1>
        <div style="overflow: auto; max-height: 600px;">' . $this->content . '</div>';
    }
}

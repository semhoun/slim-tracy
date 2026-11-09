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

/** Renders the host application's interactive console controls. */
final class ConsolePanel implements IBarPanel
{
    /**
     * @var string|null Console icon loaded while rendering the tab.
     */
    private ?string $icon = null;

    /**
     * Configures console authentication and terminal assets.
     *
     * @param array $cfg Console settings passed to the templates.
     *
     * @phpstan-param array<string, bool|int|string> $cfg
     */
    public function __construct(private array $cfg = [])
    {
    }

    /**
     * Renders the console tab and notification indicator.
     *
     * @return string Toolbar tab HTML with console initialization markup.
     */
    public function getTab(): string
    {
        // Set blinker to work with no active panel
        $head = (require __DIR__ . '/../Templates/ConsoleTabHead.phtml')(['cfg' => $this->cfg]);

        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/ConsolePanel.html');

        return $head . '
        <span title="PTY Console" class="warn_blink">
            ' . $this->icon . '&nbsp; 
        </span>
        ';
    }

    /**
     * Renders the interactive terminal container.
     *
     * @return string Console panel HTML configured for the host application.
     */
    public function getPanel(): string
    {
        return (require __DIR__ . '/../Templates/ConsolePanel.phtml')([
            'icon' => $this->icon,
            'noLogin' => $this->cfg['ConsoleNoLogin'],
            'terminalJs' => $this->cfg['ConsoleTerminalJs'],
            'terminalCss' => $this->cfg['ConsoleTerminalCss'],
        ]);
    }
}

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

/**
 * Panel Selector panel
 * based on part of https://processwire.com/talk/topic/12208-tracy-debugger/.
 */
final class PanelSelector implements IBarPanel
{
    private ?string $icon = null;

    /**
     * @param array<string, int> $cfg
     * @param array<string, int> $defcfg
     */
    public function __construct(private readonly array $cfg = [], private readonly array $defcfg = [])
    {
    }

    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/PanelSelector.html');

        return '
            <span title="Panel Selector">
                ' . $this->icon . '
            </span>';
    }

    public function getPanel(): string
    {
        return (require __DIR__ . '/../Templates/PanelSelectorHead.phtml')([])
            . (require __DIR__ . '/../Templates/PanelSelectorBody.phtml')([
                'icon' => $this->icon,
                'cfg' => $this->cfg,
                'defcfg' => $this->defcfg,
            ]);
    }
}

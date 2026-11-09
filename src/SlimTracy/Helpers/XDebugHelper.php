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

/** Renders Xdebug session controls for the configured IDE key. */
final readonly class XDebugHelper implements IBarPanel
{
    /**
     * Configures the IDE session identifier used by the controls.
     *
     * @param string $ideKey IDE key supplied when starting an Xdebug session.
     */
    public function __construct(private string $ideKey = 'PHPSTORM')
    {
    }

    /**
     * Declares that the helper has no expandable panel.
     *
     * @return false Keeps the Xdebug helper limited to toolbar controls.
     */
    public function getPanel(): false
    {
        return false;
    }

    /**
     * Renders the Xdebug session controls.
     *
     * @return string Toolbar HTML configured with the IDE session key.
     */
    public function getTab(): string
    {
        return (require __DIR__ . '/../Templates/xdebug-helper.tab.phtml')(['ideKey' => $this->ideKey]);
    }
}

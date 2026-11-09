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

final class SlimRouterPanel implements IBarPanel
{
    private ?string $icon = null;

    /**
     * @param iterable<\Slim\Interfaces\RouteInterface> $routes
     * @param array<string, string> $ver
     */
    public function __construct(private readonly iterable $routes = [], private readonly array $ver = [])
    {
    }

    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/SlimRouterPanel.html');

        return '<span title="Slim Router">' . $this->icon . '</span>';
    }

    public function getPanel(): string
    {
        return (require __DIR__ . '/../Templates/RouterBody.phtml')([
            'icon' => $this->icon,
            'ver' => $this->ver,
            'rows' => $this->getData(),
        ]);
    }

    /** @return list<array<string, string|object|false|null>> */
    private function getData(): array
    {
        $data = [];
        foreach ($this->routes as $route) {
            $data[] = RouteDescription::describe($route);
        }

        return $data;
    }
}

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

/** Displays registered Slim routes and their callable descriptions. */
final class SlimRouterPanel implements IBarPanel
{
    /**
     * @var string|null Router icon loaded while rendering the tab.
     */
    private ?string $icon = null;

    /**
     * Configures the route collection and framework label.
     *
     * @param iterable              $routes Registered routes in display order.
     * @param array<string, string> $ver    Framework version labels.
     *
     * @phpstan-param iterable<\Slim\Interfaces\RouteInterface> $routes
     */
    public function __construct(private readonly iterable $routes = [], private readonly array $ver = [])
    {
    }

    /**
     * Renders the router toolbar tab.
     *
     * @return string Toolbar HTML identifying the route panel.
     */
    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/SlimRouterPanel.html');

        return '<span title="Slim Router">' . $this->icon . '</span>';
    }

    /**
     * Renders the registered routes using their callable descriptions.
     *
     * @return string Route table HTML with the Slim version label.
     */
    public function getPanel(): string
    {
        return (require __DIR__ . '/../Templates/RouterBody.phtml')([
            'icon' => $this->icon,
            'ver' => $this->ver,
            'rows' => $this->getData(),
        ]);
    }

    /**
     * Describes each route for the panel template.
     *
     * RouteDescription is a stateless formatter with a public static API.
     *
     * @return list<array<string, string|object|false|null>> Route rows in registration order.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function getData(): array
    {
        $data = [];
        foreach ($this->routes as $route) {
            $data[] = RouteDescription::describe($route);
        }

        return $data;
    }
}

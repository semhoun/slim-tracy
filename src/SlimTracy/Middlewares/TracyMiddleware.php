<?php

declare(strict_types=1);

/**
 * Copyright 2017 1f7.wizard@gmail.com.
 * Copyright 2024 Nathanael Semhoun (nathanael@semhoun.net).
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

namespace SlimTracy\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use SlimTracy\Middlewares\Support\CollectorRegistration;
use SlimTracy\Middlewares\Support\DebuggerBootstrap;
use SlimTracy\Middlewares\Support\PanelRegistration;
use SlimTracy\Middlewares\Support\WritableContainer;
use Tracy\Debugger;

/** Integrates request collection and response panels with Slim. */
final readonly class TracyMiddleware implements MiddlewareInterface
{
    private ?PanelRegistration $panels;

    /** @param array<string, scalar|array|object|null> $settings */
    public function __construct(App $app, array $settings)
    {
        DebuggerBootstrap::configure($settings['configs'] ?? []);
        if (! Debugger::$showBar) {
            $this->panels = null;
            return;
        }

        $container = WritableContainer::fromApp($app);
        $container->set('tracy.settings', $settings);
        CollectorRegistration::register($container, $settings);
        $this->panels = new PanelRegistration($app, $settings);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($this->panels instanceof PanelRegistration && Debugger::$showBar) {
            $this->panels->register($request, $response);
        }

        return $response;
    }
}

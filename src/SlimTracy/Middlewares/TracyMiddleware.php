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
use SlimTracy\Helpers\ApiPanel;
use SlimTracy\Middlewares\Support\CollectorRegistration;
use SlimTracy\Middlewares\Support\DebuggerBootstrap;
use SlimTracy\Middlewares\Support\PanelRegistration;
use SlimTracy\Middlewares\Support\WritableContainer;
use SlimTracy\Profiling\Configuration;
use SlimTracy\Profiling\FileStore;
use SlimTracy\Profiling\RequestProfiler;
use SlimTracy\Profiling\StructuredExporter;
use Tracy\Debugger;

/** Integrates request collection and response panels with Slim. */
final readonly class TracyMiddleware implements MiddlewareInterface
{
    private ?PanelRegistration $panels;

    private ?RequestProfiler $profiler;

    private Configuration $profilerConfiguration;

    /** @param array<string, scalar|array|object|null> $settings */
    public function __construct(App $app, array $settings)
    {
        DebuggerBootstrap::configure($settings['configs'] ?? [], $app->getBasePath());
        $this->profilerConfiguration = Configuration::fromSettings($settings, $app->getBasePath());
        if (! Debugger::$showBar && ! $this->profilerConfiguration->isEnabled()) {
            $this->profiler = null;
            $this->panels = null;
            return;
        }

        $container = WritableContainer::fromApp($app);
        $container->set('tracy.settings', $settings);

        $collectorSettings = $settings;
        if ($this->profilerConfiguration->isEnabled()) {
            $collectorSettings['showDoctrinePanel'] ??= false;
            $this->profiler = new RequestProfiler(
                $this->profilerConfiguration,
                new FileStore($this->profilerConfiguration),
                new StructuredExporter($container, $settings),
            );
            if (Debugger::$showBar) {
                Debugger::getBar()->addPanel(new ApiPanel($this->profilerConfiguration), ApiPanel::class);
            }
        } else {
            $this->profiler = null;
        }

        CollectorRegistration::register($container, $collectorSettings);
        $this->panels = Debugger::$showBar ? new PanelRegistration($app, $settings) : null;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $debugRequest = $this->profilerConfiguration->excludes($request->getUri()->getPath());
        if ($debugRequest) {
            Debugger::$showBar = false;
        }

        $response = $this->profiler instanceof RequestProfiler
            ? $this->profiler->process($request, $handler)
            : $handler->handle($request);
        if (! $debugRequest && $this->panels instanceof PanelRegistration && Debugger::$showBar) {
            $this->panels->register($request, $response);
        }

        return $response;
    }
}

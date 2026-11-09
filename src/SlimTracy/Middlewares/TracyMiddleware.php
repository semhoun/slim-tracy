<?php

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

declare(strict_types=1);

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
use SlimTracy\Profiling\RequestProfiler;
use Tracy\Debugger;

/** Integrates request collection and response panels with Slim. */
final readonly class TracyMiddleware implements MiddlewareInterface
{
    /** @var PanelRegistration|null Legacy HTML panel registration when the bar is visible. */
    private ?PanelRegistration $panels;

    /** @var RequestProfiler|null Optional API collection independent of bar visibility. */
    private ?RequestProfiler $profiler;

    /** @var Configuration Effective development guard and externally visible debug path. */
    private Configuration $configuration;

    /**
     * Wire the native Tracy bootstrap and stateless composition factories once.
     *
     * @param App $app Host application whose container supplies optional integrations.
     * @param array<string, scalar|array|object|null> $settings Full Tracy configuration.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(App $app, array $settings)
    {
        DebuggerBootstrap::configure($settings['configs'] ?? [], $app->getBasePath());
        $this->configuration = Configuration::fromSettings($settings, $app->getBasePath());
        if (! Debugger::$showBar && ! $this->configuration->isEnabled()) {
            $this->profiler = null;
            $this->panels = null;
            return;
        }

        $container = WritableContainer::fromApp($app);
        $container->set('tracy.settings', $settings);

        $collectorSettings = $settings;
        $this->profiler = $this->configuration->isEnabled()
            ? RequestProfiler::fromContainer($this->configuration, $container, $settings) : null;
        if ($this->profiler instanceof RequestProfiler) {
            $collectorSettings['showDoctrinePanel'] ??= false;
            if (Debugger::$showBar) {
                Debugger::getBar()->addPanel(new ApiPanel($this->configuration), ApiPanel::class);
            }
        }

        CollectorRegistration::register($container, $collectorSettings);
        $this->panels = Debugger::$showBar ? new PanelRegistration($app, $settings) : null;
    }

    /** Collect API measurements and attach legacy panels without altering response bodies. */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $debugRequest = $this->configuration->excludes($request->getUri()->getPath());
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

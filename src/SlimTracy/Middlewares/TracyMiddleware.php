<?php

declare(strict_types=1);

/**
 * Copyright 2017 1f7.wizard@gmail.com.
 * Copyright 2024 Nathanaël Semhoun (nathanael@semhoun.net).
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

use Exception;
use Illuminate\Database\Capsule\Manager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use SlimTracy\Collectors\DoctrineCollector;
use SlimTracy\Helpers\ConsolePanel;
use SlimTracy\Helpers\DoctrinePanel;
use SlimTracy\Helpers\EloquentORMPanel;
use SlimTracy\Helpers\IncludedFiles;
use SlimTracy\Helpers\PanelSelector;
use SlimTracy\Helpers\PhpInfoPanel;
use SlimTracy\Helpers\ProfilerPanel;
use SlimTracy\Helpers\SlimContainerPanel;
use SlimTracy\Helpers\SlimEnvironmentPanel;
use SlimTracy\Helpers\SlimRequestPanel;
use SlimTracy\Helpers\SlimResponsePanel;
use SlimTracy\Helpers\SlimRouterPanel;
use SlimTracy\Helpers\TwigPanel;
use SlimTracy\Helpers\VendorVersionsPanel;
use SlimTracy\Helpers\XDebugHelper;
use Tracy\Debugger;
use Tracy\Dumper;

/**
 * Class TracyMiddleware.
 */
class TracyMiddleware implements MiddlewareInterface
{
    private readonly ?\Psr\Container\ContainerInterface $container;

    private array $versions = [
        'slim' => App::VERSION,
    ];

    private readonly \Slim\Interfaces\RouteCollectorInterface $routeCollector;

    /**
     * @throws Exception
     */
    public function __construct(
        App $app,
        private array $defcfg
    ) {
        include_once realpath(__DIR__ . '/../') . '/shortcuts.php';

        $this->container = $app->getContainer();
        $this->container->set('tracy.settings', $this->defcfg);

        $this->routeCollector = $app->getRouteCollector();
        $this->runCollectors();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $cookies = $request->getCookieParams();
        $cookies = isset($cookies['tracyPanelsEnabled']) ? json_decode($cookies['tracyPanelsEnabled']) : [];

        if (! empty($cookies)) {
            $def = array_fill_keys(array_keys($this->defcfg), null);
            $cookies = array_fill_keys($cookies, 1);
            $cfg = array_merge($def, $cookies);
        } else {
            $cfg = $this->defcfg;
        }

        // Remove ORM Panel Selectors if class not found
        if (! class_exists(\Doctrine\DBAL\Connection::class)) {
            unset($this->defcfg['showDoctrinePanel']);
        }

        if (! class_exists(\Illuminate\Database\Capsule\Manager::class)) {
            unset($this->defcfg['showEloquentORMPanel']);
        }

        if (! $this->defcfg['configs']['ConsoleEnable']) {
            unset($this->defcfg['showConsolePanel']);
        }

        if (
            isset($cfg['showEloquentORMPanel'])
            && $cfg['showEloquentORMPanel']
            && class_exists(\Illuminate\Database\Capsule\Manager::class)
        ) {
            Debugger::getBar()->addPanel(new EloquentORMPanel(
                Manager::getQueryLog(),
                $this->versions
            ));
        }

        if (
            isset($cfg['showTwigPanel'])
            && $cfg['showTwigPanel']
            && isset($this->defcfg['configs']['Container']['Twig'])
            && $this->container->has($this->defcfg['configs']['Container']['Twig'])
        ) {
            Debugger::getBar()->addPanel(new TwigPanel(
                $this->container->get($this->defcfg['configs']['Container']['Twig']),
                $this->versions
            ));
        }

        if (isset($cfg['showPhpInfoPanel']) && $cfg['showPhpInfoPanel']) {
            Debugger::getBar()->addPanel(new PhpInfoPanel());
        }

        if (isset($cfg['showSlimEnvironmentPanel']) && $cfg['showSlimEnvironmentPanel']) {
            Debugger::getBar()->addPanel(new SlimEnvironmentPanel(
                Dumper::toHtml($request->getServerParams()),
                $this->versions
            ));
        }

        if (isset($cfg['showSlimContainer']) && $cfg['showSlimContainer']) {
            Debugger::getBar()->addPanel(new SlimContainerPanel(
                Dumper::toHtml($this->container),
                $this->versions
            ));
        }

        if (isset($cfg['showSlimRouterPanel']) && $cfg['showSlimRouterPanel']) {
            Debugger::getBar()->addPanel(new SlimRouterPanel(
                $this->routeCollector->getRoutes(),
                $this->versions
            ));
        }

        if (isset($cfg['showSlimRequestPanel']) && $cfg['showSlimRequestPanel']) {
            Debugger::getBar()->addPanel(new SlimRequestPanel(
                Dumper::toHtml($request),
                $this->versions
            ));
        }

        if (isset($cfg['showSlimResponsePanel']) && $cfg['showSlimResponsePanel']) {
            Debugger::getBar()->addPanel(new SlimResponsePanel(
                Dumper::toHtml($response),
                $this->versions
            ));
        }

        if (isset($cfg['showVendorVersionsPanel']) && $cfg['showVendorVersionsPanel']) {
            Debugger::getBar()->addPanel(new VendorVersionsPanel());
        }

        if (isset($cfg['showXDebugHelper']) && $cfg['showXDebugHelper']) {
            Debugger::getBar()->addPanel(new XDebugHelper(
                $this->defcfg['configs']['XDebugHelperIDEKey']
            ));
        }

        if (isset($cfg['showIncludedFiles']) && $cfg['showIncludedFiles']) {
            Debugger::getBar()->addPanel(new IncludedFiles());
        }

        // check if enabled or blink if active critical value
        if (
            (isset($cfg['showConsolePanel']) && $cfg['showConsolePanel'])
            || isset($cfg['configs']['ConsoleNoLogin'])
            && $cfg['configs']['ConsoleNoLogin']
        ) {
            Debugger::getBar()->addPanel(new ConsolePanel(
                $this->defcfg['configs']
            ));
        }

        if (isset($cfg['showProfilerPanel']) && $cfg['showProfilerPanel']) {
            Debugger::getBar()->addPanel(new ProfilerPanel(
                $this->defcfg['configs']['ProfilerPanel']
            ));
        }

        if (
            isset($cfg['showDoctrinePanel'])
            && $cfg['showDoctrinePanel']
            && class_exists(\Doctrine\DBAL\Connection::class)
            && $this->container->has('tracy.doctrineQueries')
        ) {
            Debugger::getBar()->addPanel(new DoctrinePanel(
                $this->container->get('tracy.doctrineQueries')->getQueries(),
                $this->versions
            ));
        }

        // hardcoded without config prevent switch off
        if ($this->defcfg === null && ! is_array($this->defcfg)) {
            $this->defcfg = [];
        }

        Debugger::getBar()->addPanel(new PanelSelector(
            $cfg,
            array_diff_key($this->defcfg, ['configs' => null])
        ));

        return $response;
    }

    /**
     * @throws Exception
     */
    private function runCollectors(): void
    {
        if (
            isset($this->defcfg['showDoctrinePanel'])
            && class_exists(\Doctrine\DBAL\Connection::class)
            && isset($this->defcfg['configs']['Container']['Doctrine'])
            && $this->container->has($this->defcfg['configs']['Container']['Doctrine'])
        ) {
            new DoctrineCollector(
                $this->container,
                $this->defcfg['configs']['Container']['Doctrine']
            );
        }
    }
}

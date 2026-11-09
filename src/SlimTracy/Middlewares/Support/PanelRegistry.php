<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use Closure;
use Illuminate\Database\Capsule\Manager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use SlimTracy\Helpers\ConsolePanel;
use SlimTracy\Helpers\DoctrinePanel;
use SlimTracy\Helpers\EloquentORMPanel;
use SlimTracy\Helpers\IncludedFiles;
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
use Tracy\Dumper;
use Tracy\IBarPanel as Panel;

/**
 * Keeps panel construction lazy and preserves the toolbar's registration order.
 *
 * This composition root centrally wires the known panel types; its coupling reflects that exhaustive registry.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final readonly class PanelRegistry
{
    /** @param array<string, scalar|array|object|null> $config */
    public function __construct(private App $app, private array $config)
    {
    }

    /**
     * Builds lazy factories for the supported toolbar panels.
     *
     * Capsule exposes its shared query log through a static facade; WritableContainer is a stateless validator.
     *
     * @param ServerRequestInterface $request  Current request supplying panel details.
     * @param ResponseInterface      $response Downstream response supplying panel details.
     *
     * @return array<string, Closure(): Panel> Panel factories keyed by their selection settings.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function factories(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $container = WritableContainer::fromApp($this->app);
        $versions = ['slim' => App::VERSION];
        return [
            'showEloquentORMPanel' => static fn (): Panel => new EloquentORMPanel(Manager::getQueryLog(), $versions),
            'showTwigPanel' => fn (): Panel => new TwigPanel(
                $container->get($this->config['Container']['Twig']),
                $versions
            ),
            'showPhpInfoPanel' => static fn (): Panel => new PhpInfoPanel(),
            ...$this->requestFactories($request, $response),
            'showVendorVersionsPanel' => static fn (): Panel => new VendorVersionsPanel(),
            'showXDebugHelper' => fn (): Panel => new XDebugHelper($this->config['XDebugHelperIDEKey']),
            'showIncludedFiles' => static fn (): Panel => new IncludedFiles(),
            'showConsolePanel' => fn (): Panel => new ConsolePanel($this->config),
            'showProfilerPanel' => fn (): Panel => new ProfilerPanel($this->config['ProfilerPanel']),
            'showDoctrinePanel' => static fn (): Panel => new DoctrinePanel(
                $container->get('tracy.doctrineQueries')->getQueries(),
                $versions
            ),
        ];
    }

    /**
     * Builds lazy factories for request, response, container, and route panels.
     *
     * Tracy's Dumper provides static HTML rendering; WritableContainer is a stateless validator.
     *
     * @param ServerRequestInterface $request  Current request supplying panel details.
     * @param ResponseInterface      $response Downstream response supplying panel details.
     *
     * @return array<string, Closure(): Panel> Request-related panel factories in toolbar order.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function requestFactories(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $container = WritableContainer::fromApp($this->app);
        $router = $this->app->getRouteCollector();
        $versions = ['slim' => App::VERSION];
        return [
            'showSlimEnvironmentPanel' => static fn (): Panel => new SlimEnvironmentPanel(
                Dumper::toHtml($request->getServerParams()),
                $versions
            ),
            'showSlimContainer' => static fn (): Panel => new SlimContainerPanel(Dumper::toHtml($container), $versions),
            'showSlimRouterPanel' => static fn (): Panel => new SlimRouterPanel($router->getRoutes(), $versions),
            'showSlimRequestPanel' => static fn (): Panel => new SlimRequestPanel(Dumper::toHtml($request), $versions),
            'showSlimResponsePanel' => static fn (): Panel => new SlimResponsePanel(
                Dumper::toHtml($response),
                $versions
            ),
        ];
    }
}

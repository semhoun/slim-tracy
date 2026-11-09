<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimTracy\Helpers\ApiPanel;
use SlimTracy\Profiling\Configuration;
use Tracy\Bar;
use Tracy\Debugger;
use Tracy\DeferredContent;

/** Renders an isolated bar without the host application's sensitive panels. */
final readonly class ApiDebugPage
{
    /** @param Configuration $configuration Effective profiler settings. */
    public function __construct(private Configuration $configuration)
    {
    }

    /** Runs only after the routes' development and native-dispatch guards. */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (! $this->configuration->isEnabled() || isset($request->getQueryParams()['_tracy_bar'])) {
            return $response->withStatus(404)->withHeader('Cache-Control', 'no-store');
        }

        // The isolated bar uses Tracy's public rendering lifecycle, never host deferred content.
        // This @internal integration is pinned and tested with the native AJAX adapter.
        $storage = new NativeSessionIsolation();
        $bar = new Bar();
        $bar->addPanel(new ApiPanel($this->configuration));
        register_shutdown_function(static function () use ($bar, $storage): void {
            $bar->render(new DeferredContent($storage));
        });
        Debugger::$showBar = false;
        $response->getBody()->write(<<<'HTML'
            <!doctype html><html lang="en"><head><meta charset="utf-8">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <title>SlimTracy API Profiles</title></head><body><h1>SlimTracy API Profiles</h1>
            <p>Open the API panel in the Tracy bar. This history is shared by everyone on this server.</p>
            </body></html>
            HTML);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }
}

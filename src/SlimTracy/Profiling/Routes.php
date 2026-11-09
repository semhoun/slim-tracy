<?php

declare(strict_types=1);

namespace SlimTracy\Profiling;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use SlimTracy\Middlewares\Support\ApiDebugPage;
use Throwable;

/** Explicit host-app registration; no routes or storage access exist by mere autoloading. */
final readonly class Routes
{
    /** Keep route dispatch invokable so Slim does not rebind a static closure to its container. */
    private function __construct(
        private Configuration $configuration,
        private FileStore $store,
        private Closure $debugPage
    ) {
    }

    /**
     * Guard every endpoint before dispatch, using the shared stateless CORS policy.
     *
     * @param array<string, string> $arguments Matched route parameters, never filesystem paths.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        $configuration = $this->configuration;
        $response = $response->withHeader('Cache-Control', 'no-store');
        if (! $configuration->isEnabled()) {
            return $this->json($response, ['error' => 'Not found'], 404);
        }

        if (! Cors::allowed($request, $configuration)) {
            return $this->json($response->withAddedHeader('Vary', 'Origin'), ['error' => 'Origin refused'], 403);
        }

        $response = Cors::apply($request, $response, $configuration, $request->getMethod() === 'OPTIONS');
        if ($request->getMethod() === 'OPTIONS') {
            return $response->withStatus(204);
        }

        try {
            return $this->dispatch($request, $response, $arguments);
        } catch (Throwable) {
            error_log('SlimTracy API profiler endpoint unavailable.');
            return $this->json($response, ['error' => 'Profiler unavailable'], 503);
        }
    }

    /** Register guarded routes explicitly in the host Slim application. */
    public static function register(
        App $app,
        Configuration $configuration,
        ?FileStore $store = null,
        ?callable $debugPage = null
    ): void {
        $configuration = $configuration->withBasePath($app->getBasePath());
        $store ??= new FileStore($configuration);
        $debugPage ??= new ApiDebugPage($configuration);
        $dispatch = new self($configuration, $store, $debugPage(...));
        $app->map(['GET', 'OPTIONS'], $configuration->path, $dispatch);
        $app->map(['GET', 'OPTIONS'], $configuration->path . '/profiles', $dispatch);
        $app->map(['GET', 'OPTIONS'], $configuration->path . '/profiles/{id}', $dispatch);
        $app->map(['POST', 'OPTIONS'], $configuration->path . '/browser', $dispatch);
    }

    /** @param array<string, string> $arguments Validated route parameters. */
    private function dispatch(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        $path = rawurldecode($request->getUri()->getPath());
        if ($path === rawurldecode($this->configuration->urlPath()) && $request->getMethod() === 'GET') {
            $native = $request->getQueryParams()['_tracy_bar'] ?? null;
            if ($native !== null && $native !== 'js') {
                return $this->json($response, ['error' => 'Not found'], 404);
            }

            return ($this->debugPage)($request, $response)->withHeader('Cache-Control', 'no-store');
        }

        if ($request->getMethod() === 'POST') {
            return $this->browser($request, $response, $this->store);
        }

        if (isset($arguments['id'])) {
            $profile = $this->store->get($arguments['id']);
            return $profile === null ? $this->json($response, ['error' => 'Not found'], 404)
                : $this->json($response, $profile);
        }

        return $this->listing($request, $response);
    }

    /** Return the next bounded page without consuming stored snapshots. */
    private function listing(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $after = is_string($query['after'] ?? null) ? substr($query['after'], 0, 100) : null;
        $limit = filter_var($query['limit'] ?? 50, FILTER_VALIDATE_INT);
        return $this->json($response, ['schemaVersion' => 1] + $this->store->listing($after, $limit ? $limit : 50));
    }

    /** Accept bounded client declarations, never arbitrary fields or bodies. */
    private function browser(
        ServerRequestInterface $request,
        ResponseInterface $response,
        FileStore $store
    ): ResponseInterface {
        $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
        if ($type !== 'application/json') {
            return $this->json($response, ['error' => 'JSON required'], 415);
        }

        $body = $request->getBody()->read(4097);
        if (strlen($body) > 4096 || ! $request->getBody()->eof()) {
            return $this->json($response, ['error' => 'Telemetry too large'], 413);
        }

        $telemetry = (new BrowserTelemetry())->parse($body);
        if ($telemetry === null) {
            return $this->json($response, ['error' => 'Invalid telemetry'], 400);
        }

        return $store->attachBrowser($telemetry['id'], $telemetry['browser'])
            ? $this->json($response, ['accepted' => true], 202)
            : $this->json($response, ['error' => 'Not found'], 404);
    }

    /** @param array<string, mixed> $data */
    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}

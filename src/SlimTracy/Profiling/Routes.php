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

    /** @param array<string, string> $arguments Matched route parameters, never filesystem paths. */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        $configuration = $this->configuration;
        $store = $this->store;
        $debugPage = $this->debugPage;
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
            $path = rawurldecode($request->getUri()->getPath());
            if ($path === rawurldecode($configuration->urlPath()) && $request->getMethod() === 'GET') {
                $native = $request->getQueryParams()['_tracy_bar'] ?? null;
                if ($native !== null && $native !== 'js') {
                    return $this->json($response, ['error' => 'Not found'], 404);
                }

                return $debugPage($request, $response)->withHeader('Cache-Control', 'no-store');
            }

            if ($request->getMethod() === 'POST') {
                return $this->browser($request, $response, $store);
            }

            if (isset($arguments['id'])) {
                $profile = $store->get($arguments['id']);
                return $profile === null ? $this->json($response, ['error' => 'Not found'], 404)
                    : $this->json($response, $profile);
            }

            $query = $request->getQueryParams();
            $after = is_string($query['after'] ?? null) ? substr($query['after'], 0, 100) : null;
            $limit = filter_var($query['limit'] ?? 50, FILTER_VALIDATE_INT);
            return $this->json($response, ['schemaVersion' => 1] + $store->listing($after, $limit ? $limit : 50));
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
        $dispatch = new self($configuration, $store, Closure::fromCallable($debugPage));
        $app->map(['GET', 'OPTIONS'], $configuration->path, $dispatch);
        $app->map(['GET', 'OPTIONS'], $configuration->path . '/profiles', $dispatch);
        $app->map(['GET', 'OPTIONS'], $configuration->path . '/profiles/{id}', $dispatch);
        $app->map(['POST', 'OPTIONS'], $configuration->path . '/browser', $dispatch);
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

        $data = json_decode($body, true, 8);
        $duration = is_array($data) ? ($data['durationMs'] ?? null) : null;
        if (
            ! is_array($data) || ! is_string($data['id'] ?? null)
            || ! preg_match('/^[a-f0-9]{32}$/D', $data['id'])
            || (! is_int($duration) && ! is_float($duration)) || ! is_finite((float) $duration)
            || $duration < 0 || $duration > 86400000
            || (isset($data['schemaVersion']) && $data['schemaVersion'] !== 1)
        ) {
            return $this->json($response, ['error' => 'Invalid telemetry'], 400);
        }

        $browser = ['durationMs' => $duration];
        if (in_array($data['outcome'] ?? null, ['complete', 'network-error', 'aborted'], true)) {
            $browser['outcome'] = $data['outcome'];
        }

        if (is_string($data['method'] ?? null)) {
            $browser['method'] = StructuredExporter::text($data['method'], 32);
        }

        if (is_string($data['path'] ?? null)) {
            $path = parse_url($data['path'], PHP_URL_PATH);
            $browser['path'] = StructuredExporter::text(is_string($path) ? $path : '', 1024);
        }

        $status = $data['status'] ?? null;
        if ($status === null || (is_int($status) && $status >= 100 && $status <= 599)) {
            $browser['status'] = $data['status'] ?? null;
        }

        return $store->attachBrowser($data['id'], $browser) ? $this->json($response, ['accepted' => true], 202)
            : $this->json($response, ['error' => 'Not found'], 404);
    }

    /** @param array<string, mixed> $data */
    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}

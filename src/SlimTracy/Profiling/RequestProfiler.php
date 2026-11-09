<?php

declare(strict_types=1);

namespace SlimTracy\Profiling;

use DateTimeImmutable;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/** Measures the downstream handler without inspecting bodies or changing error handling. */
final readonly class RequestProfiler implements MiddlewareInterface
{
    public const HEADER = 'X-SlimTracy-Profile';

    /** Compose the request-scoped exporter and shared store. */
    public function __construct(
        private Configuration $configuration,
        private FileStore $store,
        private StructuredExporter $exporter
    ) {
    }

    /**
     * Compose the storage and collector dependencies at the integration boundary.
     *
     * @param Configuration $configuration Effective profiler settings.
     * @param ContainerInterface $container Host application's optional integrations.
     * @param array<string, mixed> $settings Full Tracy settings.
     *
     * @return self Request-scoped profiler with a shared snapshot store.
     */
    public static function fromContainer(
        Configuration $configuration,
        ContainerInterface $container,
        array $settings
    ): self {
        return new self(
            $configuration,
            new FileStore($configuration),
            new StructuredExporter($container, $settings, $configuration)
        );
    }

    /**
     * Preserve response streams and use the stateless export/CORS policies.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (
            ! $this->configuration->isEnabled() || $request->getMethod() === 'OPTIONS'
            || $this->configuration->excludes($request->getUri()->getPath())
            || array_key_exists('_tracy_bar', $request->getQueryParams())
        ) {
            return $handler->handle($request);
        }

        try {
            $this->exporter->start();
        } catch (Throwable) {
            error_log('SlimTracy API profiler baseline unavailable.');
            return $handler->handle($request);
        }

        $started = hrtime(true);
        $startedAt = (new DateTimeImmutable())->format('Y-m-d\TH:i:s.uP');
        $response = null;
        try {
            $response = $handler->handle($request);
        } finally {
            try {
                $profile = [
                    'schemaVersion' => 1,
                    'id' => bin2hex(random_bytes(16)),
                    'method' => StructuredExporter::text($request->getMethod(), 32),
                    'path' => StructuredExporter::text($request->getUri()->getPath(), 1024),
                    'status' => $response?->getStatusCode(),
                    'startedAt' => $startedAt,
                    'durationMs' => max(0, (hrtime(true) - $started) / 1000000),
                    'memoryBytes' => memory_get_peak_usage(true),
                    'collectors' => $this->exporter->export(),
                ];
                if ($this->store->save($profile) && $response instanceof ResponseInterface) {
                    $response = $response->withHeader(self::HEADER, $profile['id']);
                    $response = Cors::apply($request, $response, $this->configuration);
                }
            } catch (Throwable) {
                // Never log exception messages: SQL and storage paths may contain secrets.
                error_log('SlimTracy API profiler snapshot unavailable.');
            }
        }

        return $response;
    }
}

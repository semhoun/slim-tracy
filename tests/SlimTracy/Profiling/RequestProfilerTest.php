<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Profiling;

use DI\Container;
use RuntimeException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Server\RequestHandlerInterface;
use SlimTracy\Collectors\DoctrineLogger\Queries;
use SlimTracy\Profiling\FileStore;
use SlimTracy\Profiling\RequestProfiler;
use SlimTracy\Profiling\StructuredExporter;
use Tracy\Debugger;

/** Verifies response preservation, URL redaction, and profiler failure isolation. */
final class RequestProfilerTest extends ProfilerTestCase
{
    /** Verify response identity and safe request metadata, excluding reserved requests. */
    public function testRequestPreservesBodyStatusAndExcludesSensitiveUrlData(): void
    {
        $configuration = $this->configuration();
        $store = new FileStore($configuration);
        $profiler = new RequestProfiler($configuration, $store, new StructuredExporter(null, [], $configuration));
        $response = new Response(422, ['Content-Type' => 'application/json'], '{"error":"invalid"}');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        $result = $profiler->process(new ServerRequest('POST', 'https://api.test/orders?token=secret'), $handler);
        self::assertSame(422, $result->getStatusCode());
        self::assertSame($response->getBody(), $result->getBody());
        self::assertSame('{"error":"invalid"}', (string) $result->getBody());
        $profileId = $result->getHeaderLine(RequestProfiler::HEADER);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $profileId);
        self::assertSame('/orders', $store->get($profileId)['path']);
        self::assertSame(422, $store->get($profileId)['status']);
        foreach (
            [
            new ServerRequest('OPTIONS', '/orders'),
            new ServerRequest('GET', '/_tracy/api/profiles'),
            ] as $request
        ) {
            self::assertSame($response, $profiler->process($request, $handler));
        }
        self::assertCount(1, $store->listing()['profiles']);
    }

    /** Verify an escaping exception retains identity and records no invented status. */
    public function testEscapingExceptionRetainsIdentityAndUnknownStatus(): void
    {
        $configuration = $this->configuration();
        $store = new FileStore($configuration);
        $profiler = new RequestProfiler($configuration, $store, new StructuredExporter(null, [], $configuration));
        $exception = new RuntimeException('original');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException($exception);
        try {
            $profiler->process(new ServerRequest('GET', '/failure'), $handler);
            self::fail('Exception expected');
        } catch (RuntimeException $caught) {
            self::assertSame($exception, $caught);
        }
        self::assertNull($store->listing()['profiles'][0]['status']);
    }

    /** Verify storage failures cannot change the application response. */
    public function testStorageFailureDoesNotChangeResponse(): void
    {
        $configuration = $this->configuration(['storageDirectory' => '/dev/null/profiles']);
        $profiler = new RequestProfiler(
            $configuration,
            new FileStore($configuration),
            new StructuredExporter(null, [], $configuration)
        );
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = new Response(201, [], 'unchanged');
        $handler->method('handle')->willReturn($response);
        $log = ini_set('error_log', '/dev/null');
        try {
            self::assertSame($response, $profiler->process(new ServerRequest('GET', '/'), $handler));
        } finally {
            ini_set('error_log', $log);
        }
    }

    /** Verify exporter failure and production mode never create profile storage. */
    public function testExportFailureDoesNotChangeResponseAndInactiveRequestCreatesNoDirectory(): void
    {
        $container = new Container();
        $queries = new Queries();
        $queries->addQuery(0.1, 'SELECT 1');
        $container->set('tracy.doctrineQueries', $queries);
        $configuration = $this->configuration(['sqlFilter' => static function (): never {
            throw new RuntimeException('sensitive SQL error');
        }]);
        $profiler = new RequestProfiler(
            $configuration,
            new FileStore($configuration),
            new StructuredExporter($container, [], $configuration)
        );
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = new Response(204);
        $handler->method('handle')->willReturn($response);
        $log = ini_set('error_log', '/dev/null');
        try {
            self::assertSame($response, $profiler->process(new ServerRequest('GET', '/'), $handler));
        } finally {
            ini_set('error_log', $log);
        }
        self::assertDirectoryDoesNotExist($this->directory);
        Debugger::$productionMode = true;
        self::assertSame($response, $profiler->process(new ServerRequest('GET', '/'), $handler));
        self::assertDirectoryDoesNotExist($this->directory);
    }
}

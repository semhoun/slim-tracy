<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Profiling;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Slim\App;
use SlimTracy\Profiling\Cors;
use SlimTracy\Profiling\FileStore;
use SlimTracy\Profiling\RequestProfiler;
use SlimTracy\Profiling\Routes;
use Tracy\Debugger;

/** Verifies profiler route guards, browser payload limits, and host CORS preservation. */
final class RoutesTest extends ProfilerTestCase
{
    /**
     * Exercise static route registration with disabled configuration and residual data.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testDisabledRoutesCannotReadResidualData(): void
    {
        $store = new FileStore($this->configuration());
        $profile = $this->profile();
        $store->save($profile);
        $app = new App(new Psr17Factory());
        Routes::register($app, $this->configuration(['enabled' => false]), $store);
        foreach (['', '/profiles', '/profiles/' . $profile['id']] as $path) {
            $response = $app->handle(new ServerRequest('GET', 'https://api.test/_tracy/api' . $path));
            self::assertSame(404, $response->getStatusCode());
            self::assertStringNotContainsString($profile['id'], (string) $response->getBody());
        }
    }

    /**
     * Exercise the public static CORS policy transformer against existing host headers.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testCorsPreservesHostPolicy(): void
    {
        $configuration = $this->configuration(['origins' => ['https://spa.test']]);
        $response = new Response(200, [
            'Access-Control-Allow-Origin' => 'https://host.test',
            'Access-Control-Allow-Methods' => 'GET',
            'Access-Control-Allow-Headers' => 'X-Host',
            'Access-Control-Expose-Headers' => 'X-Host',
            'Vary' => 'Accept-Encoding',
        ]);
        $result = Cors::apply(
            new ServerRequest('OPTIONS', 'https://api.test/', ['Origin' => 'https://spa.test']),
            $response,
            $configuration,
            true
        );
        self::assertSame('https://host.test', $result->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('GET', $result->getHeaderLine('Access-Control-Allow-Methods'));
        self::assertSame('X-Host', $result->getHeaderLine('Access-Control-Allow-Headers'));
        self::assertStringContainsString('X-Host', $result->getHeaderLine('Access-Control-Expose-Headers'));
        self::assertStringContainsString(
            RequestProfiler::HEADER,
            $result->getHeaderLine('Access-Control-Expose-Headers')
        );
        self::assertStringContainsString('Accept-Encoding', $result->getHeaderLine('Vary'));
        self::assertStringContainsString('Origin', $result->getHeaderLine('Vary'));
    }

    /**
     * Exercise static route registration through its HTTP guards and payload limits.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testRoutesCorsJsonLimitsAndProductionResidualGuard(): void
    {
        $configuration = $this->configuration(['origins' => ['https://spa.test']]);
        $store = new FileStore($configuration);
        $profile = $this->profile();
        $store->save($profile);
        $app = new App(new Psr17Factory());
        Routes::register($app, $configuration, $store);
        $base = 'https://api.test/_tracy/api';
        $request = new ServerRequest('GET', $base . '/profiles', ['Origin' => 'https://spa.test']);
        $response = $app->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('https://spa.test', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertCount(1, json_decode((string) $response->getBody(), true)['profiles']);
        self::assertSame(403, $app->handle($request->withHeader('Origin', 'https://spa.test.evil'))->getStatusCode());
        self::assertSame(204, $app->handle($request->withMethod('OPTIONS'))->getStatusCode());
        $post = new ServerRequest(
            'POST',
            $base . '/browser',
            ['Origin' => 'https://spa.test', 'Content-Type' => 'application/json'],
            json_encode([
                'id' => $profile['id'], 'durationMs' => 10, 'path' => '/safe?secret=yes',
                'authorization' => 'secret', 'source' => 'server',
            ])
        );
        self::assertSame(202, $app->handle($post)->getStatusCode());
        $browser = $store->get($profile['id'])['browser'];
        self::assertSame('/safe', $browser['path']);
        self::assertSame('client', $browser['source']);
        self::assertArrayNotHasKey('authorization', $browser);
        self::assertSame(415, $app->handle($post->withHeader('Content-Type', 'text/plain'))->getStatusCode());
        $large = new ServerRequest(
            'POST',
            $base . '/browser',
            ['Content-Type' => 'application/json'],
            str_repeat(' ', 4097)
        );
        self::assertSame(413, $app->handle($large)->getStatusCode());
        self::assertSame(400, $app->handle(new ServerRequest(
            'POST',
            $base . '/browser',
            ['Content-Type' => 'application/json'],
            '{}'
        ))->getStatusCode());
        self::assertSame(404, $app->handle(new ServerRequest('GET', $base . '?_tracy_bar=content'))->getStatusCode());
        Debugger::$productionMode = true;
        self::assertSame(404, $app->handle($request)->getStatusCode());
        self::assertSame(404, $app->handle(new ServerRequest(
            'GET',
            $base . '/profiles/' . $profile['id']
        ))->getStatusCode());
        self::assertSame(404, $app->handle(new ServerRequest('GET', $base))->getStatusCode());
    }
}

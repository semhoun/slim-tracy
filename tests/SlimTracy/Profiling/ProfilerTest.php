<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Profiling;

use DI\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use SlimTracy\Collectors\DoctrineLogger\Queries;
use SlimTracy\Helpers\Profiler\Profile;
use SlimTracy\Helpers\Profiler\ProfilerService;
use SlimTracy\Profiling\Configuration;
use SlimTracy\Profiling\FileStore;
use SlimTracy\Profiling\RequestProfiler;
use SlimTracy\Profiling\Routes;
use SlimTracy\Profiling\StructuredExporter;
use Tracy\Debugger;

final class ProfilerTest extends TestCase
{
    private string $directory;
    private ?bool $productionMode;

    protected function setUp(): void
    {
        $this->productionMode = Debugger::$productionMode;
        Debugger::$productionMode = false;
        $this->directory = sys_get_temp_dir() . '/slim-tracy-test-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        Debugger::$productionMode = $this->productionMode;
        if (is_dir($this->directory)) {
            foreach (new \DirectoryIterator($this->directory) as $file) {
                if (! $file->isDot()) {
                    unlink($file->getPathname());
                }
            }
            rmdir($this->directory);
        }
    }

    public function testGuardRequiresExplicitEnabledAndResolvedDevelopment(): void
    {
        foreach ([null, false, 1, 'true', true] as $enabled) {
            foreach ([null, false, true] as $mode) {
                Debugger::$productionMode = $mode;
                self::assertSame($enabled === true && $mode === false, $this->configuration(['enabled' => $enabled])->isEnabled());
            }
        }
        self::assertFalse(Configuration::fromSettings([])->isEnabled());
        self::assertSame('/_tracy/api', $this->configuration(['path' => '/'])->path);
        self::assertSame('/_tracy/api', $this->configuration(['path' => '//'])->path);
        self::assertTrue($this->configuration()->excludes('/%5ftracy/api/profiles'));
        self::assertFalse($this->configuration()->excludes('/_tracy/apiculture'));
    }

    public function testStoreNonDestructivePrivateTruncatedBoundedAndExpired(): void
    {
        $configuration = $this->configuration(['maxProfiles' => 2, 'maxProfileBytes' => 600, 'maxTotalBytes' => 1200]);
        $store = new FileStore($configuration);
        $first = $this->profile();
        $first['collectors'] = ['doctrine' => ['sql' => str_repeat('secret', 1000)]];
        self::assertTrue($store->save($first));
        self::assertTrue($store->get($first['id'])['truncated']);
        self::assertSame([], $store->get($first['id'])['collectors']);
        self::assertNotNull($store->get($first['id']));
        self::assertSame(0700, fileperms($this->directory) & 0777);
        self::assertSame(0600, fileperms($this->directory . '/' . $first['id'] . '.json') & 0777);
        $store->save($this->profile());
        $store->save($this->profile());
        self::assertCount(2, $store->listing()['profiles']);
        self::assertNull($store->get('../outside'));
        $profile = $store->listing()['profiles'][0];
        touch($this->directory . '/' . $profile['id'] . '.json', time() - 601);
        clearstatcache();
        self::assertNull($store->get($profile['id']));
        self::assertCount(1, $store->listing()['profiles']);
        Debugger::$productionMode = true;
        self::assertNull($store->get($first['id']));
        self::assertFalse($store->save($first));
        self::assertSame([], $store->listing()['profiles']);
    }

    public function testIncrementalPaginationAndBrowserUpdates(): void
    {
        $store = new FileStore($this->configuration());
        $profile = $this->profile();
        $store->save($profile);
        $page = $store->listing(null, 1);
        self::assertCount(1, $page['profiles']);
        self::assertSame([], $store->listing($page['nextCursor'])['profiles']);
        self::assertTrue($store->attachBrowser($profile['id'], ['durationMs' => 15]));
        $update = $store->listing($page['nextCursor']);
        self::assertSame('client', $update['profiles'][0]['browser']['source']);
        self::assertSame(15, $update['profiles'][0]['browser']['durationMs']);
        self::assertFalse($store->attachBrowser(str_repeat('0', 32), []));
    }

    public function testBrowserAttachmentCannotExtendOriginalExpiry(): void
    {
        $store = new FileStore($this->configuration());
        $profile = $this->profile();
        $store->save($profile);
        $stored = $store->get($profile['id']);
        self::assertTrue($store->attachBrowser($profile['id'], ['durationMs' => 2]));
        self::assertSame($stored['expiresAt'], $store->get($profile['id'])['expiresAt']);
        $stored['expiresAt'] = time() - 1;
        $file = $this->directory . '/' . $profile['id'] . '.json';
        file_put_contents($file, json_encode($stored));
        touch($file);
        clearstatcache();
        self::assertNull($store->get($profile['id']));
        self::assertFalse($store->attachBrowser($profile['id'], ['durationMs' => 2]));
        self::assertSame([], $store->listing()['profiles']);
        self::assertFileDoesNotExist($file);
    }

    public function testAggregateBytesAndLongPathsAreBounded(): void
    {
        $store = new FileStore($this->configuration(['maxProfileBytes' => 512, 'maxTotalBytes' => 700]));
        for ($index = 0; $index < 5; ++$index) {
            $profile = $this->profile();
            $profile['path'] = '/' . str_repeat('a', 1024);
            self::assertTrue($store->save($profile));
            self::assertNotNull($store->get($profile['id']));
        }
        $bytes = 0;
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if ($file->getExtension() === 'json') {
                $bytes += $file->getSize();
            }
        }
        self::assertLessThanOrEqual(700, $bytes);
    }

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

    public function testOptionalEloquentAndTwigUseStructuredUnits(): void
    {
        $capsule = new \Illuminate\Database\Capsule\Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->getConnection()->enableQueryLog();
        $capsule->getConnection()->select('SELECT ? AS value', ['binding-secret']);
        $twig = new \Twig\Profiler\Profile();
        $twig->leave();
        $container = new Container();
        $container->set('twig.profile', $twig);
        $settings = ['configs' => ['AjaxProfiler' => ['enabled' => true], 'Container' => ['Twig' => 'twig.profile']]];
        $data = (new StructuredExporter($container, $settings))->export();
        self::assertCount(1, $data['eloquent']['queries']);
        self::assertSame($capsule->getConnection()->getQueryLog()[0]['time'], $data['eloquent']['queries'][0]['durationMs']);
        self::assertSame($twig->getDuration() * 1000, $data['twig']['profiles'][0]['durationMs']);
        self::assertStringNotContainsString('binding-secret', json_encode($data));
        $capsule->getConnection()->disableQueryLog();
        self::assertArrayNotHasKey('eloquent', (new StructuredExporter($container, $settings))->export());
    }

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
        $result = \SlimTracy\Profiling\Cors::apply(
            new ServerRequest('OPTIONS', 'https://api.test/', ['Origin' => 'https://spa.test']),
            $response,
            $configuration,
            true
        );
        self::assertSame('https://host.test', $result->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('GET', $result->getHeaderLine('Access-Control-Allow-Methods'));
        self::assertSame('X-Host', $result->getHeaderLine('Access-Control-Allow-Headers'));
        self::assertStringContainsString('X-Host', $result->getHeaderLine('Access-Control-Expose-Headers'));
        self::assertStringContainsString(RequestProfiler::HEADER, $result->getHeaderLine('Access-Control-Expose-Headers'));
        self::assertStringContainsString('Accept-Encoding', $result->getHeaderLine('Vary'));
        self::assertStringContainsString('Origin', $result->getHeaderLine('Vary'));
    }

    public function testExporterRedactsBindingsAndConvertsSecondsAndHostSqlFilter(): void
    {
        $container = new Container();
        $queries = new Queries();
        $queries->addQuery(0.012, "SELECT 'secret' WHERE id = ?", ['binding-secret']);
        $container->set('tracy.doctrineQueries', $queries);
        $settings = ['configs' => ['AjaxProfiler' => ['enabled' => true, 'sqlFilter' => static fn (string $sql): string => str_replace("'secret'", '?', $sql)]]];
        ProfilerService::getInstance()->addProfile(new Profile(['start_label' => 'manual', 'secret' => 'metadata-secret'], 0.01, 0.007, 8, 4));
        $data = (new StructuredExporter($container, $settings))->export();
        self::assertSame(12.0, $data['doctrine']['queries'][0]['durationMs']);
        self::assertSame('SELECT ? WHERE id = ?', $data['doctrine']['queries'][0]['sql']);
        self::assertSame(7.0, $data['manual']['profiles'][array_key_last($data['manual']['profiles'])]['durationMs']);
        self::assertStringNotContainsString('binding-secret', json_encode($data));
        self::assertStringNotContainsString('metadata-secret', json_encode($data));
        self::assertArrayNotHasKey('twig', $data);
        self::assertArrayNotHasKey('doctrine', (new StructuredExporter(null, $settings))->export());
    }

    public function testSqlStringTruncationIsExplicit(): void
    {
        $container = new Container();
        $queries = new Queries();
        $queries->addQuery(0.001, str_repeat('x', 9000));
        $container->set('tracy.doctrineQueries', $queries);
        $data = (new StructuredExporter($container, [], $this->configuration()))->export();
        self::assertSame(8192, strlen($data['doctrine']['queries'][0]['sql']));
        self::assertTrue($data['doctrine']['queries'][0]['truncated']);
        self::assertTrue($data['doctrine']['truncated']);
    }

    public function testRepeatedRequestsExportOnlyCurrentSqlAndManualEntries(): void
    {
        $container = new Container();
        $configuration = $this->configuration();
        $store = new FileStore($configuration);
        $queries = new Queries();
        $capsule = new \Illuminate\Database\Capsule\Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->getConnection()->enableQueryLog();
        $twig = new \Twig\Profiler\Profile();
        $container->set('twig.profile', $twig);
        $container->set('tracy.doctrineQueries', $queries);
        $settings = ['configs' => ['Container' => ['Twig' => 'twig.profile']]];
        $profiler = new RequestProfiler($configuration, $store, new StructuredExporter($container, $settings, $configuration));
        $handler = $this->createMock(RequestHandlerInterface::class);
        $iteration = 0;
        $handler->method('handle')->willReturnCallback(static function () use ($queries, $capsule, $twig, &$iteration): Response {
            ++$iteration;
            $queries->addQuery(0.001, 'SELECT ' . $iteration);
            $capsule->getConnection()->select('SELECT ' . $iteration);
            $twig->addProfile(new \Twig\Profiler\Profile('request-' . $iteration, 'template'));
            ProfilerService::getInstance()->addProfile(new Profile(['start_label' => 'request-' . $iteration], 0.001, 0.001));
            return new Response(200);
        });
        $first = $profiler->process(new ServerRequest('GET', '/first'), $handler);
        $second = $profiler->process(new ServerRequest('GET', '/second'), $handler);
        $data = $store->get($second->getHeaderLine(RequestProfiler::HEADER));
        self::assertSame('SELECT 2', $data['collectors']['doctrine']['queries'][0]['sql']);
        self::assertCount(1, $data['collectors']['doctrine']['queries']);
        self::assertSame('request-2', $data['collectors']['manual']['profiles'][0]['label']);
        self::assertCount(1, $data['collectors']['manual']['profiles']);
        self::assertSame('SELECT 2', $data['collectors']['eloquent']['queries'][0]['sql']);
        self::assertCount(1, $data['collectors']['eloquent']['queries']);
        self::assertSame('request-2', $data['collectors']['twig']['profiles'][0]['template']);
        self::assertCount(1, $data['collectors']['twig']['profiles']);
        self::assertSame('SELECT 1', $store->get($first->getHeaderLine(RequestProfiler::HEADER))['collectors']['doctrine']['queries'][0]['sql']);
    }

    public function testStartDoesNotResolveLateOptionalServices(): void
    {
        $container = new Container();
        $handled = false;
        $container->set('tracy.doctrineQueries', \DI\factory(static function () use (&$handled): Queries {
            self::assertTrue($handled, 'Optional services must not be constructed before the handler.');
            $queries = new Queries();
            $queries->addQuery(0.001, 'SELECT late');
            return $queries;
        }));
        $configuration = $this->configuration();
        $store = new FileStore($configuration);
        $profiler = new RequestProfiler($configuration, $store, new StructuredExporter($container, [], $configuration));
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(static function () use (&$handled): Response {
            $handled = true;
            return new Response(200);
        });
        $response = $profiler->process(new ServerRequest('GET', '/late'), $handler);
        self::assertSame('SELECT late', $store->get($response->getHeaderLine(RequestProfiler::HEADER))['collectors']['doctrine']['queries'][0]['sql']);
    }

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
        $id = $result->getHeaderLine(RequestProfiler::HEADER);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
        self::assertSame('/orders', $store->get($id)['path']);
        self::assertSame(422, $store->get($id)['status']);
        foreach ([new ServerRequest('OPTIONS', '/orders'), new ServerRequest('GET', '/_tracy/api/profiles')] as $request) {
            self::assertSame($response, $profiler->process($request, $handler));
        }
        self::assertCount(1, $store->listing()['profiles']);
    }

    public function testEscapingExceptionRetainsIdentityAndUnknownStatus(): void
    {
        $configuration = $this->configuration();
        $store = new FileStore($configuration);
        $profiler = new RequestProfiler($configuration, $store, new StructuredExporter(null, [], $configuration));
        $exception = new \RuntimeException('original');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException($exception);
        try {
            $profiler->process(new ServerRequest('GET', '/failure'), $handler);
            self::fail('Exception expected');
        } catch (\RuntimeException $caught) {
            self::assertSame($exception, $caught);
        }
        self::assertNull($store->listing()['profiles'][0]['status']);
    }

    public function testStorageFailureDoesNotChangeResponse(): void
    {
        $configuration = $this->configuration(['storageDirectory' => '/dev/null/profiles']);
        $profiler = new RequestProfiler($configuration, new FileStore($configuration), new StructuredExporter(null, [], $configuration));
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

    public function testExportFailureDoesNotChangeResponseAndInactiveRequestCreatesNoDirectory(): void
    {
        $container = new Container();
        $queries = new Queries();
        $queries->addQuery(0.1, 'SELECT 1');
        $container->set('tracy.doctrineQueries', $queries);
        $configuration = $this->configuration(['sqlFilter' => static function (): never {
            throw new \RuntimeException('sensitive SQL error');
        }]);
        $profiler = new RequestProfiler($configuration, new FileStore($configuration), new StructuredExporter($container, [], $configuration));
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
        $post = new ServerRequest('POST', $base . '/browser', ['Origin' => 'https://spa.test', 'Content-Type' => 'application/json'], json_encode([
            'id' => $profile['id'], 'durationMs' => 10, 'path' => '/safe?secret=yes', 'authorization' => 'secret', 'source' => 'server',
        ]));
        self::assertSame(202, $app->handle($post)->getStatusCode());
        $browser = $store->get($profile['id'])['browser'];
        self::assertSame('/safe', $browser['path']);
        self::assertSame('client', $browser['source']);
        self::assertArrayNotHasKey('authorization', $browser);
        self::assertSame(415, $app->handle($post->withHeader('Content-Type', 'text/plain'))->getStatusCode());
        $large = new ServerRequest('POST', $base . '/browser', ['Content-Type' => 'application/json'], str_repeat(' ', 4097));
        self::assertSame(413, $app->handle($large)->getStatusCode());
        self::assertSame(400, $app->handle(new ServerRequest('POST', $base . '/browser', ['Content-Type' => 'application/json'], '{}'))->getStatusCode());
        self::assertSame(404, $app->handle(new ServerRequest('GET', $base . '?_tracy_bar=content'))->getStatusCode());
        Debugger::$productionMode = true;
        self::assertSame(404, $app->handle($request)->getStatusCode());
        self::assertSame(404, $app->handle(new ServerRequest('GET', $base . '/profiles/' . $profile['id']))->getStatusCode());
        self::assertSame(404, $app->handle(new ServerRequest('GET', $base))->getStatusCode());
    }

    private function configuration(array $overrides = []): Configuration
    {
        return Configuration::fromSettings(['configs' => ['AjaxProfiler' => array_replace([
            'enabled' => true, 'storageDirectory' => $this->directory,
        ], $overrides)]]);
    }

    private function profile(): array
    {
        return [
            'schemaVersion' => 1, 'id' => bin2hex(random_bytes(16)), 'method' => 'GET', 'path' => '/test',
            'status' => 200, 'startedAt' => '2026-11-09T12:00:00Z', 'durationMs' => 1, 'memoryBytes' => 1024, 'collectors' => [],
        ];
    }
}

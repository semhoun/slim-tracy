<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Middlewares;

use Doctrine\DBAL\Configuration as DoctrineConfiguration;
use Doctrine\DBAL\DriverManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Http\Server\RequestHandlerInterface;
use SlimTracy\Helpers\ApiPanel;
use SlimTracy\Middlewares\Support\DebuggerBootstrap;
use SlimTracy\Middlewares\TracyMiddleware;
use SlimTracy\Profiling\Configuration;
use SlimTracy\Profiling\FileStore;
use SlimTracy\Profiling\Routes;
use Tests\BaseTestCase;
use Tracy\Debugger;

/** Verifies opt-in API profiling and its native Tracy integration boundaries. */
#[RunTestsInSeparateProcesses]
final class AjaxProfilerMiddlewareTest extends BaseTestCase
{
    /** @var string Private profile storage for one test. */
    private string $directory;

    /** Allocate a unique storage path without creating it. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/slim-tracy-middleware-' . bin2hex(random_bytes(8));
    }

    /** Remove profile files after each isolated test process. */
    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    /**
     * Exercise DBAL's connection factory and the static configuration factory end to end.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testCollectsDoctrineWithoutBarOrPanelSelectionAndPreservesResponseStream(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $app = $this->createApp();
        $doctrine = new DoctrineConfiguration();
        $app->getContainer()->set('doctrine.config', $doctrine);
        $settings = ['configs' => [
            'ShowBar' => false,
            'Container' => ['Doctrine' => 'doctrine.config'],
            'AjaxProfiler' => ['enabled' => true, 'storageDirectory' => $this->directory],
        ]];
        $middleware = new TracyMiddleware($app, $settings);
        self::assertCount(1, $doctrine->getMiddlewares());
        $request = (new Psr17Factory())->createServerRequest('POST', '/api?token=secret')
            ->withCookieParams(['tracyPanelsEnabled' => '[]']);
        $response = new Response(201, ['Content-Type' => 'application/json'], '{"ok":true}');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(static function () use ($doctrine, $response) {
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $doctrine)->fetchOne('SELECT 1');
            return $response;
        });
        $result = $middleware->process($request, $handler);
        self::assertSame(201, $result->getStatusCode());
        self::assertSame($response->getBody(), $result->getBody());
        self::assertSame(0, $result->getBody()->tell());
        $profileId = $result->getHeaderLine('X-SlimTracy-Profile');
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $profileId);
        $profile = (new FileStore(Configuration::fromSettings($settings)))->get($profileId);
        self::assertSame('/api', $profile['path']);
        self::assertStringNotContainsString('secret', json_encode($profile));
        self::assertNotEmpty($profile['collectors']['doctrine']);
    }

    /**
     * Inspect Tracy's public static bar accessor after production-mode processing.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testProductionNeverInstallsApiPanelOrCollects(): void
    {
        Debugger::$productionMode = Debugger::Production;
        $middleware = new TracyMiddleware($this->createApp(), ['configs' => [
            'ShowBar' => true,
            'AjaxProfiler' => ['enabled' => true, 'storageDirectory' => $this->directory],
        ]]);
        $response = new Response();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        self::assertSame(
            $response,
            $middleware->process((new Psr17Factory())->createServerRequest('GET', '/'), $handler)
        );
        self::assertNull(Debugger::getBar()->getPanel(ApiPanel::class));
        self::assertDirectoryDoesNotExist($this->directory);
        self::assertCount(1, Debugger::$customJsFiles);
        self::assertStringEndsWith('/web/disable-native-ajax.js', Debugger::$customJsFiles[0]);
    }

    /**
     * Exercise static bootstrap against the exact globals native Tracy dispatch reads.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function testNativeDeferredDispatchIsRemovedBeforeEnableForDisabledDebugPage(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $_SERVER['REQUEST_URI'] = '/_tracy/api?_tracy_bar=content.private';
        $_SERVER['HTTP_X_TRACY_AJAX'] = 'malicious';
        $_GET['_tracy_bar'] = 'content.private';
        DebuggerBootstrap::configure([]);
        self::assertArrayNotHasKey('_tracy_bar', $_GET);
        self::assertArrayNotHasKey('HTTP_X_TRACY_AJAX', $_SERVER);
    }

    /**
     * Exercise repeated calls of the public static bootstrap API.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testDisablingProfilerRetainsNativeSuppression(): void
    {
        Debugger::$productionMode = Debugger::Development;
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => false]]);
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => false]]);
        self::assertCount(1, Debugger::$customJsFiles);
        self::assertStringEndsWith('/web/disable-native-ajax.js', Debugger::$customJsFiles[0]);
    }

    /** Verify reserved preflight requests disable the host bar without collecting. */
    public function testDebugPreflightSuppressesGlobalHostBarWithoutCollecting(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $middleware = new TracyMiddleware($this->createApp(), ['configs' => [
            'ShowBar' => true,
            'AjaxProfiler' => ['enabled' => true, 'storageDirectory' => $this->directory],
        ]]);
        $response = new Response(204);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        $request = (new Psr17Factory())->createServerRequest('OPTIONS', '/_tracy/api/browser');
        self::assertSame($response, $middleware->process($request, $handler));
        self::assertFalse(Debugger::$showBar);
        self::assertDirectoryDoesNotExist($this->directory);
    }

    /**
     * Exercise the static configuration factory's incoming-origin interpretation.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testNativeClientTargetsOwnOriginNotIncomingCorsFrontendOrigins(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $panel = new ApiPanel(Configuration::fromSettings(['configs' => [
            'AjaxProfiler' => ['enabled' => true, 'origins' => ['https://frontend.example']],
        ]]));
        self::assertStringContainsString('data-slim-tracy-client', $panel->getTab());
        self::assertStringContainsString('data-allowed-origins="[]"', $panel->getTab());
        self::assertStringNotContainsString('frontend.example', $panel->getTab());
        self::assertStringContainsString('data-allowed-origins="[]"', $panel->getPanel());
    }

    /**
     * Exercise static route registration and configuration factories with a Slim base path.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testSlimBasePathIsAppliedToDebugPageExclusionsAndClientUrls(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $app = $this->createApp();
        $app->setBasePath('/app');
        $settings = ['configs' => [
            'ShowBar' => true,
            'AjaxProfiler' => ['enabled' => true, 'storageDirectory' => $this->directory],
        ]];
        $middleware = new TracyMiddleware($app, $settings);
        Routes::register($app, Configuration::fromSettings($settings));
        $app->add($middleware);
        $app->addRoutingMiddleware();
        $response = $app->handle((new Psr17Factory())->createServerRequest('GET', '/app/_tracy/api'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('SlimTracy API Profiles', (string) $response->getBody());
        self::assertFalse($response->hasHeader('X-SlimTracy-Profile'));
        self::assertDirectoryDoesNotExist($this->directory);
        $panel = new ApiPanel(Configuration::fromSettings($settings, $app->getBasePath()));
        self::assertStringContainsString('data-base-url="/app/_tracy/api"', $panel->getTab());
    }

    /**
     * Inspect native Tracy's static storage API after dispatch globals are sanitized.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function testBasePathProtectsNativeDeferredDispatchEvenForEncodedPaths(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $_SERVER['REQUEST_URI'] = '/app/%5ftracy/api?_tracy_bar=content.private';
        $_GET['_tracy_bar'] = 'content.private';
        $app = $this->createApp();
        $app->setBasePath('/app');
        new TracyMiddleware($app, ['configs' => ['AjaxProfiler' => ['enabled' => true]]]);
        self::assertArrayNotHasKey('_tracy_bar', $_GET);
        self::assertFalse(Debugger::getSessionStorage()->isAvailable());
    }
}

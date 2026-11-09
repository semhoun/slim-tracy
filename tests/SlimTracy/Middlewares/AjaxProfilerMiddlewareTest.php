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

#[RunTestsInSeparateProcesses]
final class AjaxProfilerMiddlewareTest extends BaseTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/slim-tracy-middleware-' . bin2hex(random_bytes(8));
    }

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
        $id = $result->getHeaderLine('X-SlimTracy-Profile');
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
        $profile = (new FileStore(Configuration::fromSettings($settings)))->get($id);
        self::assertSame('/api', $profile['path']);
        self::assertStringNotContainsString('secret', json_encode($profile));
        self::assertNotEmpty($profile['collectors']['doctrine']);
    }

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
        self::assertSame($response, $middleware->process((new Psr17Factory())->createServerRequest('GET', '/'), $handler));
        self::assertNull(Debugger::getBar()->getPanel(ApiPanel::class));
        self::assertDirectoryDoesNotExist($this->directory);
        self::assertCount(1, Debugger::$customJsFiles);
        self::assertStringEndsWith('/web/disable-native-ajax.js', Debugger::$customJsFiles[0]);
    }

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

    public function testDisablingProfilerRetainsNativeSuppression(): void
    {
        Debugger::$productionMode = Debugger::Development;
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => false]]);
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => false]]);
        self::assertCount(1, Debugger::$customJsFiles);
        self::assertStringEndsWith('/web/disable-native-ajax.js', Debugger::$customJsFiles[0]);
    }

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

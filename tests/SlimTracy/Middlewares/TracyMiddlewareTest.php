<?php

namespace Tests\SlimTracy\Middlewares;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Illuminate\Database\Capsule\Manager;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Slim\App;
use SlimTracy\Helpers\ConsolePanel;
use SlimTracy\Helpers\PanelSelector;
use SlimTracy\Helpers\SlimRequestPanel;
use SlimTracy\Helpers\SlimResponsePanel;
use SlimTracy\Middlewares\Support\PanelRegistry;
use SlimTracy\Middlewares\TracyMiddleware;
use Tests\BaseTestCase;
use Tracy\Debugger;
use Twig\Profiler\Profile;

/** Verifies panel selection, optional collectors, and middleware lifecycle ordering. */
#[RunTestsInSeparateProcesses]
class TracyMiddlewareTest extends BaseTestCase
{
    /** Verify selected panels and optional collectors in a complete Slim request. */
    public function testTracyMiddleware(): void
    {
        $app = $this->createApp();
        $container = $app->getContainer();
        $configuration = new Configuration();
        $container->set('doctrine.config', $configuration);
        $container->set('twig_profile', new Profile());
        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $capsule->getConnection()->enableQueryLog();

        $settings = $this->cfg['settings']['tracy'];
        $settings['showDoctrinePanel'] = true;
        Debugger::$showBar = true;
        $app->add(new TracyMiddleware($app, $settings));
        $this->assertSame($settings, $container->get('tracy.settings'));
        $this->assertCount(1, $configuration->getMiddlewares());

        $app->get('/hello/{name}', function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ) use (
            $configuration,
            $capsule
        ) {
            $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
            $connection->fetchOne('SELECT 1');
            $capsule->getConnection()->select('SELECT 2');
            $response->getBody()->write('<html><body>Hello, ' . $args['name'] . '</body></html>');
            return $response->withHeader('Content-Type', 'text/html');
        });
        $panels = array_keys(array_filter($settings, fn ($key) => $key !== 'configs', ARRAY_FILTER_USE_KEY));
        $panels = array_unique(array_merge($panels, [
            'showTwigPanel', 'showEloquentORMPanel', 'showPhpInfoPanel', 'showSlimRouterPanel',
            'showSlimEnvironmentPanel', 'showSlimContainer', 'showProfilerPanel', 'showVendorVersionsPanel',
            'showXDebugHelper', 'showIncludedFiles', 'showConsolePanel',
        ]));
        $request = (new Psr17Factory())->createServerRequest('GET', '/hello/World')
            ->withCookieParams(['tracyPanelsEnabled' => json_encode(array_values($panels))]);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Hello, World', (string) $response->getBody());
        $this->assertCount(1, $container->get('tracy.doctrineQueries')->getQueries());
        $this->assertCount(1, $capsule->getConnection()->getQueryLog());
        foreach (
            [\SlimTracy\Helpers\DoctrinePanel::class, \SlimTracy\Helpers\TwigPanel::class,
            \SlimTracy\Helpers\EloquentORMPanel::class, \SlimTracy\Helpers\PanelSelector::class] as $panel
        ) {
            $this->assertInstanceOf($panel, Debugger::getBar()->getPanel($panel));
        }
    }

    /** Verify default settings apply when no selection cookie is supplied. */
    public function testTracyMiddlewareWithoutCookies(): void
    {
        $app = $this->createApp();
        Debugger::$showBar = true;
        $app->add(new TracyMiddleware($app, $this->cfg['settings']['tracy']));
        $app->get('/hello/{name}', function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ) {
            $response->getBody()->write('Hello, ' . $args['name']);
            return $response;
        });
        $request = (new Psr17Factory())->createServerRequest('GET', '/hello/World');
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello, World', (string) $response->getBody());
        $this->assertFalse($app->getContainer()->has('tracy.doctrineQueries'));
    }

    /** Verify direct middleware invocation preserves the handler response. */
    public function testTracyMiddlewareDirectly(): void
    {
        Debugger::$showBar = true;
        $middleware = new TracyMiddleware($this->createApp(), $this->cfg['settings']['tracy']);
        $request = (new Psr17Factory())->createServerRequest('GET', '/');
        $response = new Response(201);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);
        $this->assertSame($response, $middleware->process($request, $handler));
    }

    /**
     * Inspect Tracy's public static bar accessor after cookie-based panel selection.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testCookieOverridesSettingsAndMissingIntegrationsAreIgnored(): void
    {
        $app = $this->createApp();
        Debugger::$showBar = true;
        $settings = $this->cfg['settings']['tracy'];
        $settings['showSlimRequestPanel'] = false;
        $middleware = new TracyMiddleware($app, $settings);
        $request = (new Psr17Factory())->createServerRequest('GET', '/')
            ->withCookieParams(['tracyPanelsEnabled' => json_encode([
                'showSlimRequestPanel', 'showTwigPanel', 'showDoctrinePanel',
            ])]);
        $response = new Response();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);
        $this->assertSame($response, $middleware->process($request, $handler));
        $bar = Debugger::getBar();
        $this->assertInstanceOf(
            \SlimTracy\Helpers\SlimRequestPanel::class,
            $bar->getPanel(\SlimTracy\Helpers\SlimRequestPanel::class)
        );
        $this->assertNull($bar->getPanel(\SlimTracy\Helpers\SlimResponsePanel::class));
        $this->assertNull($bar->getPanel(\SlimTracy\Helpers\TwigPanel::class));
        $this->assertNull($bar->getPanel(\SlimTracy\Helpers\DoctrinePanel::class));
    }

    /** Verify disabled bars do not register collectors or container settings. */
    public function testDisabledBarPassesThroughWithoutRegisteringCollectors(): void
    {
        $app = $this->createApp();
        $settings = $this->cfg['settings']['tracy'];
        $settings['configs']['ShowBar'] = false;
        $middleware = new TracyMiddleware($app, $settings);
        $request = (new Psr17Factory())->createServerRequest('GET', '/');
        $response = new Response();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);
        $this->assertSame($response, $middleware->process($request, $handler));
        $this->assertFalse($app->getContainer()->has('tracy.settings'));
    }

    /** Verify invalid selection cookies fall back to default panels. */
    #[DataProvider('invalidPanelCookies')]
    public function testInvalidCookieFallsBackToDefaults(string|array $cookie): void
    {
        $app = $this->createApp();
        Debugger::$showBar = true;
        $middleware = new TracyMiddleware($app, $this->cfg['settings']['tracy']);
        $request = (new Psr17Factory())->createServerRequest('GET', '/')
            ->withCookieParams(['tracyPanelsEnabled' => $cookie]);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response());

        $middleware->process($request, $handler);

        self::assertInstanceOf(SlimRequestPanel::class, Debugger::getBar()->getPanel(SlimRequestPanel::class));
        self::assertInstanceOf(SlimResponsePanel::class, Debugger::getBar()->getPanel(SlimResponsePanel::class));
    }

    /** Supply malformed and unsupported panel selection cookie values. */
    public static function invalidPanelCookies(): array
    {
        return [['invalid JSON'], ['null'], ['false'], ['"showTwigPanel"'], ['[]'], [['unexpected array']]];
    }

    /** Verify cookies and no-login settings cannot override a disabled console. */
    public function testDisabledConsoleCannotBeEnabledByCookieOrNoLogin(): void
    {
        Debugger::$showBar = true;
        $settings = $this->cfg['settings']['tracy'];
        $settings['configs']['ConsoleEnable'] = false;
        $settings['configs']['ConsoleNoLogin'] = true;
        $middleware = new TracyMiddleware($this->createApp(), $settings);
        $request = (new Psr17Factory())->createServerRequest('GET', '/')
            ->withCookieParams(['tracyPanelsEnabled' => '["showConsolePanel"]']);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response());

        $middleware->process($request, $handler);

        self::assertNull(Debugger::getBar()->getPanel(ConsolePanel::class));
        self::assertStringNotContainsString(
            'showConsolePanel',
            Debugger::getBar()->getPanel(PanelSelector::class)->getPanel()
        );
    }

    /** Verify optional service factories remain lazy when unselected. */
    public function testUnselectedFactoriesDoNotResolveOptionalServices(): void
    {
        Debugger::$showBar = true;
        $app = $this->createApp();
        $app->getContainer()->set('twig_profile', static function (): never {
            throw new LogicException('An unselected Twig factory must remain lazy.');
        });
        $middleware = new TracyMiddleware($app, $this->cfg['settings']['tracy']);
        $request = (new Psr17Factory())->createServerRequest('GET', '/')
            ->withCookieParams(['tracyPanelsEnabled' => '["showSlimRequestPanel"]']);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response());

        $middleware->process($request, $handler);

        self::assertNull(Debugger::getBar()->getPanel(\SlimTracy\Helpers\TwigPanel::class));
        self::assertInstanceOf(SlimRequestPanel::class, Debugger::getBar()->getPanel(SlimRequestPanel::class));
    }

    /** Verify a missing container produces an actionable error. */
    public function testMissingContainerHasClearError(): void
    {
        Debugger::$showBar = true;
        $app = new App(new Psr17Factory());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tracy requires a container with a public set() method.');
        new TracyMiddleware($app, $this->cfg['settings']['tracy']);
    }

    /** Reject a read-only container without resolving its services. */
    public function testReadOnlyContainerHasClearErrorWithoutResolvingServices(): void
    {
        Debugger::$showBar = true;
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');
        $container->expects($this->never())->method('has');
        $app = new App(new Psr17Factory(), $container);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tracy requires a container with a public set() method.');
        new TracyMiddleware($app, $this->cfg['settings']['tracy']);
    }

    /** Verify disabled bars do not require a writable container. */
    public function testDisabledBarDoesNotRequireContainerAndStaysInactive(): void
    {
        $settings = $this->cfg['settings']['tracy'];
        $settings['configs']['ShowBar'] = false;
        $middleware = new TracyMiddleware(new App(new Psr17Factory()), $settings);
        Debugger::$showBar = true;
        $request = (new Psr17Factory())->createServerRequest('GET', '/');
        $response = new Response(202);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);

        self::assertSame($response, $middleware->process($request, $handler));
        self::assertNull(Debugger::getBar()->getPanel(PanelSelector::class));
    }

    /** Verify panel factory enumeration is ordered and lazy. */
    public function testFactoriesPreservePanelOrderWithoutConstructingPanels(): void
    {
        $registry = new PanelRegistry($this->createApp(), []);
        $request = (new Psr17Factory())->createServerRequest('GET', '/');

        $factories = $registry->factories($request, new Response());

        self::assertSame([
            'showEloquentORMPanel', 'showTwigPanel', 'showPhpInfoPanel', 'showSlimEnvironmentPanel',
            'showSlimContainer', 'showSlimRouterPanel', 'showSlimRequestPanel', 'showSlimResponsePanel',
            'showVendorVersionsPanel', 'showXDebugHelper', 'showIncludedFiles', 'showConsolePanel',
            'showProfilerPanel', 'showDoctrinePanel',
        ], array_keys($factories));
        self::assertNull(Debugger::getBar()->getPanel(SlimRequestPanel::class));
    }

    /** Verify every visible console selection remains checked in the selector. */
    #[\PHPUnit\Framework\Attributes\DataProvider('consoleSelectionCases')]
    public function testVisibleConsoleRemainsCheckedInThePanelSelector(
        int $configured,
        int $noLogin,
        ?string $cookie
    ): void {
        Debugger::$showBar = true;
        $settings = $this->cfg['settings']['tracy'];
        $settings['showConsolePanel'] = $configured;
        $settings['configs']['ConsoleNoLogin'] = $noLogin;
        $settings['configs']['ConsoleEnable'] = true;
        $middleware = new TracyMiddleware($this->createApp(), $settings);
        $request = (new Psr17Factory())->createServerRequest('GET', '/');
        if ($cookie !== null) {
            $request = $request->withCookieParams(['tracyPanelsEnabled' => $cookie]);
        }
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response());
        $middleware->process($request, $handler);

        self::assertInstanceOf(ConsolePanel::class, Debugger::getBar()->getPanel(ConsolePanel::class));
        $selector = Debugger::getBar()->getPanel(PanelSelector::class);
        self::assertMatchesRegularExpression(
            '/name="selectedPanels\[\]"\s+checked="checked"\s+value="showConsolePanel"/',
            $selector->getPanel()
        );
    }

    /** Supply settings, cookie, and no-login console selections. */
    public static function consoleSelectionCases(): array
    {
        return [
            'settings' => [1, 0, null],
            'cookie' => [0, 0, '["showConsolePanel"]'],
            'without login' => [0, 1, '["showSlimRequestPanel"]'],
        ];
    }

    /** Verify panel registration happens after the application handler. */
    public function testPanelsAreRegisteredOnlyAfterTheHandlerReturns(): void
    {
        Debugger::$showBar = true;
        $middleware = new TracyMiddleware($this->createApp(), $this->cfg['settings']['tracy']);
        $request = (new Psr17Factory())->createServerRequest('GET', '/');
        $response = new Response();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturnCallback(static function () use ($response) {
            self::assertNull(Debugger::getBar()->getPanel(SlimRequestPanel::class));
            return $response;
        });

        self::assertSame($response, $middleware->process($request, $handler));
        self::assertInstanceOf(SlimRequestPanel::class, Debugger::getBar()->getPanel(SlimRequestPanel::class));
    }
}

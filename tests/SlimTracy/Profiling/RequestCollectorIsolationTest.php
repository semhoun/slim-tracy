<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Profiling;

use DI\Container;
use Illuminate\Database\Capsule\Manager;
use Twig\Profiler\Profile as TwigProfile;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Server\RequestHandlerInterface;
use SlimTracy\Collectors\DoctrineLogger\Queries;
use SlimTracy\Helpers\Profiler\Profile;
use SlimTracy\Helpers\Profiler\ProfilerService;
use SlimTracy\Profiling\FileStore;
use SlimTracy\Profiling\RequestProfiler;
use SlimTracy\Profiling\StructuredExporter;

/** Verifies request-local collector offsets and lazy optional service resolution. */
final class RequestCollectorIsolationTest extends ProfilerTestCase
{
    /**
     * Populate the static manual-profiler service during two consecutive requests.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testRepeatedRequestsExportOnlyCurrentSqlAndManualEntries(): void
    {
        $container = new Container();
        $configuration = $this->configuration();
        $store = new FileStore($configuration);
        $queries = new Queries();
        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->getConnection()->enableQueryLog();
        $twig = new TwigProfile();
        $container->set('twig.profile', $twig);
        $container->set('tracy.doctrineQueries', $queries);
        $settings = ['configs' => ['Container' => ['Twig' => 'twig.profile']]];
        $profiler = new RequestProfiler(
            $configuration,
            $store,
            new StructuredExporter($container, $settings, $configuration)
        );
        $handler = $this->createMock(RequestHandlerInterface::class);
        $iteration = 0;
        $handler->method('handle')->willReturnCallback(static function () use (
            $queries,
            $capsule,
            $twig,
            &$iteration
        ): Response {
            ++$iteration;
            $queries->addQuery(0.001, 'SELECT ' . $iteration);
            $capsule->getConnection()->select('SELECT ' . $iteration);
            $twig->addProfile(new TwigProfile('request-' . $iteration, 'template'));
            ProfilerService::getInstance()->addProfile(new Profile(
                ['start_label' => 'request-' . $iteration],
                0.001,
                0.001
            ));
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
        self::assertSame(
            'SELECT 1',
            $store->get($first->getHeaderLine(RequestProfiler::HEADER))['collectors']['doctrine']['queries'][0]['sql']
        );
    }

    /** Verify collector startup does not eagerly resolve an optional container service. */
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
        self::assertSame(
            'SELECT late',
            $store->get($response->getHeaderLine(RequestProfiler::HEADER))
                ['collectors']['doctrine']['queries'][0]['sql']
        );
    }
}

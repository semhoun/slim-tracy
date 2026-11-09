<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Profiling;

use DI\Container;
use Illuminate\Database\Capsule\Manager;
use Twig\Profiler\Profile as TwigProfile;
use SlimTracy\Collectors\DoctrineLogger\Queries;
use SlimTracy\Helpers\Profiler\Profile;
use SlimTracy\Helpers\Profiler\ProfilerService;
use SlimTracy\Profiling\StructuredExporter;

/** Verifies optional collectors, normalized units, and exported-data redaction. */
final class StructuredExporterTest extends ProfilerTestCase
{
    /** Verify optional collector units without leaking bound SQL parameters. */
    public function testOptionalEloquentAndTwigUseStructuredUnits(): void
    {
        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->getConnection()->enableQueryLog();
        $capsule->getConnection()->select('SELECT ? AS value', ['binding-secret']);
        $twig = new TwigProfile();
        $twig->leave();
        $container = new Container();
        $container->set('twig.profile', $twig);
        $settings = ['configs' => ['AjaxProfiler' => ['enabled' => true], 'Container' => ['Twig' => 'twig.profile']]];
        $data = (new StructuredExporter($container, $settings))->export();
        self::assertCount(1, $data['eloquent']['queries']);
        self::assertSame(
            $capsule->getConnection()->getQueryLog()[0]['time'],
            $data['eloquent']['queries'][0]['durationMs']
        );
        self::assertSame($twig->getDuration() * 1000, $data['twig']['profiles'][0]['durationMs']);
        self::assertStringNotContainsString('binding-secret', json_encode($data));
        $capsule->getConnection()->disableQueryLog();
        self::assertArrayNotHasKey('eloquent', (new StructuredExporter($container, $settings))->export());
    }

    /**
     * Populate the public static profiler service to verify redaction and unit conversion.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testExporterRedactsBindingsAndConvertsSecondsAndHostSqlFilter(): void
    {
        $container = new Container();
        $queries = new Queries();
        $queries->addQuery(0.012, "SELECT 'secret' WHERE id = ?", ['binding-secret']);
        $container->set('tracy.doctrineQueries', $queries);
        $settings = ['configs' => ['AjaxProfiler' => [
            'enabled' => true,
            'sqlFilter' => static fn (string $sql): string => str_replace("'secret'", '?', $sql),
        ]]];
        ProfilerService::getInstance()->addProfile(new Profile(
            ['start_label' => 'manual', 'secret' => 'metadata-secret'],
            0.01,
            0.007,
            8,
            4
        ));
        $data = (new StructuredExporter($container, $settings))->export();
        self::assertSame(12.0, $data['doctrine']['queries'][0]['durationMs']);
        self::assertSame('SELECT ? WHERE id = ?', $data['doctrine']['queries'][0]['sql']);
        self::assertSame(7.0, $data['manual']['profiles'][array_key_last($data['manual']['profiles'])]['durationMs']);
        self::assertStringNotContainsString('binding-secret', json_encode($data));
        self::assertStringNotContainsString('metadata-secret', json_encode($data));
        self::assertArrayNotHasKey('twig', $data);
        self::assertArrayNotHasKey('doctrine', (new StructuredExporter(null, $settings))->export());
    }

    /** Verify SQL string truncation is marked on both query and collector. */
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
}

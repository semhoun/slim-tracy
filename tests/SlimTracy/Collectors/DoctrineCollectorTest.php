<?php

namespace Tests\SlimTracy\Collectors;

use DI\Container;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use SlimTracy\Collectors\DoctrineCollector;
use SlimTracy\Collectors\DoctrineLogger\Queries;
use Tests\BaseTestCase;

class DoctrineCollectorTest extends BaseTestCase
{
    public function testMissingContainerOrServiceDoesNotRegisterCollector(): void
    {
        $this->assertInstanceOf(DoctrineCollector::class, new DoctrineCollector());
        $container = new Container();
        new DoctrineCollector($container, 'missing');
        $this->assertFalse($container->has('tracy.doctrineQueries'));
    }

    public function testCollectorPreservesExistingMiddlewaresAndLogsDbalQueries(): void
    {
        $container = new Container();
        $configuration = new Configuration();
        $existing = $this->createMock(\Doctrine\DBAL\Driver\Middleware::class);
        $existing->method('wrap')->willReturnArgument(0);
        $configuration->setMiddlewares([$existing]);
        $container->set('config', $configuration);
        new DoctrineCollector($container, 'config');

        $this->assertSame($existing, $configuration->getMiddlewares()[0]);
        $this->assertCount(2, $configuration->getMiddlewares());
        $queries = $container->get('tracy.doctrineQueries');
        $this->assertInstanceOf(Queries::class, $queries);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $this->assertSame(42, $connection->fetchOne('SELECT ?', [42], [ParameterType::INTEGER]));
        $logged = $queries->getQueries();
        $this->assertCount(1, $logged);
        $query = array_values($logged)[0];
        $this->assertSame('SELECT ?', $query['sql']);
        $this->assertContains(42, $query['params']);
        $this->assertGreaterThanOrEqual(0, $query['executionMS']);
    }

    public function testCollectorPreservesBoundLobResources(): void
    {
        $container = new Container();
        $configuration = new Configuration();
        $container->set('config', $configuration);
        new DoctrineCollector($container, 'config');
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, 'binary payload');
        rewind($stream);

        try {
            $result = $connection->fetchOne(
                'SELECT ?',
                [$stream],
                [ParameterType::LARGE_OBJECT]
            );
            $this->assertSame('binary payload', $result);
            $queries = $container->get('tracy.doctrineQueries')->getQueries();
            $this->assertCount(1, $queries);
            $params = array_values($queries[0]['params']);
            $this->assertSame($stream, $params[0]);
            $this->assertIsResource($params[0]);
            $this->assertContains(ParameterType::LARGE_OBJECT, $queries[0]['types']);
        } finally {
            fclose($stream);
        }
    }

    public function testCollectorAcceptsSharedOrmConfiguration(): void
    {
        $configuration = new \Doctrine\ORM\Configuration();
        $configuration->setMetadataDriverImpl(new AttributeDriver([]));
        $configuration->setProxyDir(sys_get_temp_dir());
        $configuration->setProxyNamespace('Tests\\Proxies');
        $container = new Container();
        $container->set('orm.config', $configuration);
        new DoctrineCollector($container, 'orm.config');
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $manager = new EntityManager($connection, $configuration);
        $this->assertSame(7, $manager->getConnection()->fetchOne('SELECT 7'));
        $this->assertCount(1, $container->get('tracy.doctrineQueries')->getQueries());
    }

    public function testCollectorRejectsConnectionInsteadOfConfiguration(): void
    {
        $container = new Container();
        $container->set('dbal', DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Neither Doctrine DBAL neither ORM Configuration not found');
        new DoctrineCollector($container, 'dbal');
    }
}

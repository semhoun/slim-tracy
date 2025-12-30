<?php

declare(strict_types=1);

namespace SlimTracy\Collectors\DoctrineLogger;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class Driver extends AbstractDriverMiddleware
{
    public function __construct(DriverInterface $driver, private readonly Queries $queries)
    {
        parent::__construct($driver);
    }

    /**
     * {@inheritDoc}
     */
    public function connect(array $params): DriverConnection
    {
        return new Connection(
            parent::connect($params),
            $this->queries,
        );
    }
}

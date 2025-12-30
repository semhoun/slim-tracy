<?php

declare(strict_types=1);

namespace SlimTracy\Collectors\DoctrineLogger;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

final readonly class Middleware implements MiddlewareInterface
{
    public function __construct(private Queries $queries)
    {
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new Driver($driver, $this->queries);
    }
}

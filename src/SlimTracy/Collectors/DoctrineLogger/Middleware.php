<?php

declare(strict_types=1);

namespace SlimTracy\Collectors\DoctrineLogger;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

/** Install the collecting driver decorator through DBAL's middleware contract. */
final readonly class Middleware implements MiddlewareInterface
{
    /** Share one collector across the drivers wrapped by this middleware. */
    public function __construct(private Queries $queries)
    {
    }

    /** Decorate the supplied driver without opening a connection. */
    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new Driver($driver, $this->queries);
    }
}

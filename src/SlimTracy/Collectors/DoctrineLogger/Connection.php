<?php

declare(strict_types=1);

namespace SlimTracy\Collectors\DoctrineLogger;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DriverStatement;

final class Connection extends AbstractConnectionMiddleware
{
    public function __construct(ConnectionInterface $connection, private readonly Queries $queries)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): DriverStatement
    {
        return new Statement(
            parent::prepare($sql),
            $this->queries,
            $sql,
        );
    }

    public function query(string $sql): Result
    {
        $start = microtime(true);
        try {
            return parent::query($sql);
        } finally {
            $end = microtime(true);
            $this->queries->addQuery($end - $start, $sql);
        }
    }

    public function exec(string $sql): int
    {
        $start = microtime(true);
        try {
            return parent::exec($sql);
        } finally {
            $end = microtime(true);
            $this->queries->addQuery($end - $start, $sql);
        }
    }

    public function beginTransaction(): void
    {
        $start = microtime(true);
        try {
            parent::beginTransaction();
        } finally {
            $end = microtime(true);
            $this->queries->addQuery($end - $start, 'START TRANSACTION');
        }
    }

    public function commit(): void
    {
        $start = microtime(true);
        try {
            parent::commit();
        } finally {
            $end = microtime(true);
            $this->queries->addQuery($end - $start, 'COMMIT');
        }
    }

    public function rollBack(): void
    {
        $start = microtime(true);
        try {
            parent::rollBack();
        } finally {
            $end = microtime(true);
            $this->queries->addQuery($end - $start, 'ROLLBACK');
        }
    }
}

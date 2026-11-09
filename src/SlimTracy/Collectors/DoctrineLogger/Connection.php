<?php

declare(strict_types=1);

namespace SlimTracy\Collectors\DoctrineLogger;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DriverStatement;

/** Record query and transaction timings around an existing DBAL driver connection. */
final class Connection extends AbstractConnectionMiddleware
{
    /** Share the collector used by this connection and its prepared statements. */
    public function __construct(ConnectionInterface $connection, private readonly Queries $queries)
    {
        parent::__construct($connection);
    }

    /** Wrap a prepared statement so its bindings and execution timings can be collected. */
    public function prepare(string $sql): DriverStatement
    {
        return new Statement(
            parent::prepare($sql),
            $this->queries,
            $sql,
        );
    }

    /** Record elapsed seconds for a direct query, including failed executions. */
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

    /** Record elapsed seconds for a direct statement while preserving its affected-row count. */
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

    /** Begin a transaction and record its timing even if the driver fails. */
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

    /** Commit the transaction and record its timing even if the driver fails. */
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

    /** Roll back the transaction and record its timing even if the driver fails. */
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

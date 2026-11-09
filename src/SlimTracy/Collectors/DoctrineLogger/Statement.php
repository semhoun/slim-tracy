<?php

declare(strict_types=1);

namespace SlimTracy\Collectors\DoctrineLogger;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;

/** Capture bound values and execution timings around a DBAL driver statement. */
final class Statement extends AbstractStatementMiddleware
{
    /** @var array<array-key, scalar|array|object|resource|null> Bound driver values, including LOB streams. */
    private array $params = [];

    /** @var array<ParameterType> */
    private array $types = [];

    /** @internal This statement can be only instantiated by its connection. */
    public function __construct(
        StatementInterface $statement,
        private readonly Queries $queries,
        private readonly string $sql
    ) {
        parent::__construct($statement);
    }

    /** Retain the bound value and type for query reporting before forwarding to DBAL. */
    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->params[$param] = $value;
        $this->types[$param] = $type;

        parent::bindValue($param, $value, $type);
    }

    /** Execute the driver statement and record timing even when execution fails. */
    public function execute(): ResultInterface
    {
        $start = microtime(true);
        try {
            return parent::execute();
        } finally {
            $end = microtime(true);
            $this->queries->addQuery($end - $start, $this->sql, $this->params, $this->types);
        }
    }
}

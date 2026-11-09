<?php

declare(strict_types=1);

namespace SlimTracy\Collectors\DoctrineLogger;

use Doctrine\DBAL\ParameterType;

final class Queries
{
    /**
     * @var list<array{sql: string, params: array<array-key, scalar|array|object|resource|null>|null,
     *     types: array<ParameterType>|null, executionMS: float}>
     */
    private array $queries = [];

    /**
     * Logs a SQL statement somewhere.
     *
     * @param string $sql SQL statement
     * @param array<array-key, scalar|array|object|resource|null>|null $params Driver values, including LOB streams.
     * @param ?array<ParameterType> $types
     */
    public function addQuery(
        float $duration,
        string $sql,
        ?array $params = null,
        ?array $types = null
    ): void {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'types' => $types,
            'executionMS' => $duration,
        ];
    }

    /**
     * @return list<array{sql: string, params: array<array-key, scalar|array|object|resource|null>|null,
     *     types: array<ParameterType>|null, executionMS: float}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }
}

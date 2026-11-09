<?php

declare(strict_types=1);

namespace SlimTracy\Helpers;

/**
 * Aggregates the completed Doctrine statements and renders their rows.
 *
 * @phpstan-type SqlLog array{sql: string, executionMS?: float,
 *     params: array<scalar|resource|null>|null, types: array<\Doctrine\DBAL\ParameterType>|null}
 */
final class SqlQueryTable
{
    /**
     * Renders completed statements and sums their execution times.
     *
     * SqlParameters provides stateless value and type formatting through its public static API.
     *
     * @param iterable $logs Collected Doctrine statements, including incomplete records.
     *
     * @return array{html: string, count: int, time: string} Table HTML, completed count, and formatted total seconds.
     *
     * @phpstan-param iterable<SqlLog> $logs
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public static function render(iterable $logs): array
    {
        $html = '<thead><tr><th>Cnt</th><th>Time (s)</th><th>SQL</th><th>Params</th><th>Types</th></tr></thead>';
        $time = 0;
        $count = 0;
        foreach ($logs as $log) {
            if (isset($log['executionMS'])) {
                $time += $log['executionMS'];
                ++$count;
                $html .= sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td><pre>%s</pre></td><td><pre>%s</pre></td></tr>',
                    $count,
                    number_format($log['executionMS'], 8),
                    $log['sql'],
                    SqlParameters::values($log['params']),
                    SqlParameters::types($log['types'])
                );
            }
        }

        return ['html' => $html, 'count' => $count, 'time' => number_format($time, 8)];
    }
}

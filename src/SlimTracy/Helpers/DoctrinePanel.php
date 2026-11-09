<?php

/**
 * Copyright 2017 1f7.wizard@gmail.com.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace SlimTracy\Helpers;

use Tracy\IBarPanel;

/**
 * Displays completed Doctrine statements and their aggregate duration.
 */
final class DoctrinePanel implements IBarPanel
{
    /**
     * @var string|null Doctrine icon loaded while rendering the tab.
     */
    private ?string $icon = null;

    /**
     * @var string Rendered query table rows.
     */
    private readonly string $parsed;

    /**
     * @var integer Number of completed statements.
     */
    private readonly int $count;

    /**
     * @var string Aggregate execution time formatted in seconds.
     */
    private readonly string $time;

    /**
     * Builds a table from the collected Doctrine statements.
     *
     * SqlQueryTable provides stateless query rendering through its public static API.
     *
     * @param iterable              $logs Collected statement records.
     * @param array<string, string> $ver  Framework version labels.
     *
     * @phpstan-param iterable<array{sql: string, executionMS?: float,
     *     params: array<scalar|resource|null>|null, types: array<\Doctrine\DBAL\ParameterType>|null}> $logs
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(iterable $logs = [], private array $ver = [])
    {
        $table = SqlQueryTable::render($logs);
        $this->time = $table['time'];
        $this->count = $table['count'];
        $this->parsed = $table['html'];
    }

    /**
     * Renders the statement count and duration summary.
     *
     * @return string Toolbar HTML with the total query time in milliseconds.
     */
    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/DoctrinePanel.html');

        return '
        <span class="tracy-label" title="Doctrine DBAL Panel">
            ' . $this->icon . ' (' . $this->count . ' / ' . round($this->time * 1000, 2) . ' ms)
        </span>';
    }

    /**
     * Renders the collected statements and totals.
     *
     * @return string Doctrine query table HTML.
     */
    public function getPanel(): string
    {
        return '
        <h1>' . $this->icon . ' &nbsp; Slim ' . $this->ver['slim'] . ' / Doctrine DBAL</h1>
        <div class="tracy-inner doctrinePanel">
            <p>
                <table width="100%">' . $this->parsed . '
                    <tr class="yes">
                        <th><b>' . $this->count . '</b></th>
                        <th style="white-space: nowrap"><b>' . $this->time . ' s</b></th>
                        <th colspan="3">total time</th>
                    </tr>
                </table>
            </p>
        </div>';
    }
}

<?php

declare(strict_types=1);

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

namespace SlimTracy\Helpers;

use Tracy\IBarPanel;

/**
 * Class DoctrinePanel.
 */
final class DoctrinePanel implements IBarPanel
{
    private ?string $icon = null;

    private readonly string $parsed;

    private readonly int $count;

    private readonly string $time;

    /**
     * @param iterable<array{sql: string, executionMS?: float,
     *     params: array<scalar|resource|null>|null, types: array<\Doctrine\DBAL\ParameterType>|null}> $logs
     * @param array<string, string> $ver
     */
    public function __construct(iterable $logs = [], private array $ver = [])
    {
        $table = SqlQueryTable::render($logs);
        $this->time = $table['time'];
        $this->count = $table['count'];
        $this->parsed = $table['html'];
    }

    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/DoctrinePanel.html');

        return '
        <span class="tracy-label" title="Doctrine DBAL Panel">
            ' . $this->icon . ' (' . $this->count . ' / ' . round($this->time * 1000, 2) . ' ms)
        </span>';
    }

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

<?php

/**
 * Copyright 2016 1f7.wizard@gmail.com.
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

/** Displays Eloquent queries, bindings, and aggregate execution time. */
final class EloquentORMPanel implements IBarPanel
{
    /**
     * @var integer Number of collected queries.
     */
    private readonly int $count;

    /**
     * @var string Rendered query table rows.
     */
    private readonly string $parsed;

    /**
     * @var integer|float|null Aggregate query duration in milliseconds after parsing.
     */
    private int|float|null $time = null;

    /**
     * @var string|null Eloquent icon loaded while rendering the tab.
     */
    private ?string $icon = null;

    /**
     * Prepares the collected Eloquent queries for display.
     *
     * @param array                 $data Query log records with millisecond timings.
     * @param array<string, string> $ver  Framework version labels.
     *
     * @phpstan-param array<array{time: int|float, query: string, bindings: array<scalar|null>}> $data
     */
    public function __construct(array $data = [], private array $ver = [])
    {
        $this->count = count($data);
        $this->parsed = $this->parse($data);
    }

    /**
     * Renders the query count and total duration.
     *
     * @return string Eloquent toolbar summary HTML.
     */
    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/EloquentORMPanel.html');

        return '
        <span title="DB Query Info">' . $this->icon . '
            <span class="tracy-label">' . $this->count . ' / ' . $this->time . '</span> ms
        </span>';
    }

    /**
     * Renders the query log and timing totals.
     *
     * @return string Eloquent query table HTML.
     */
    public function getPanel(): string
    {
        return '
        <h1>' . $this->icon . ' &nbsp; Slim ' . $this->ver['slim'] . ' / Eloquent ORM</h1>
        <div class="tracy-inner">
            <p>
                <table width="100%">' . $this->parsed . '
                    <tr class="yes">
                        <th><b>' . $this->count . '</b></th><th><b>' . $this->time . ' ms</b></th>
                        <th>Total</th>
                    </tr>
                </table>
            </p>
        </div>';
    }

    /**
     * Builds the query table column headings.
     *
     * @return string Table header HTML for counts, timings, and bindings.
     */
    private function getHeader(): string
    {
        return '<thead><tr><th><b>Count</b></th><th><b>Time,&nbsp;ms</b></th><th>Query / Bindings</th></tr></thead>';
    }

    /**
     * Provides the format used for each query row.
     *
     * @return string HTML format with count, duration, and query placeholders.
     */
    private function getBaseRow(): string
    {
        return '<tr><td>%s</td><td>%s</td><td>%s</td></tr>';
    }

    /**
     * Renders query rows and accumulates their execution time.
     *
     * @param array $data Query log records with millisecond timings.
     *
     * @return string Table header and query rows without the totals row.
     *
     * @phpstan-param array<array{time: int|float, query: string, bindings: array<scalar|null>}> $data
     */
    private function parse(array $data): string
    {
        $return = $this->getHeader();
        $time = 0;
        $cnt = 0;
        foreach ($data as $var) {
            $time += $var['time'];
            $row = $this->getBaseRow();
            $bind = '<span class="tracy-dump-hash"><hr />';
            foreach ($var['bindings'] ?? [] as $k => $v) {
                $bind .= sprintf('[%s => %s]<br />', $k, $v);
            }

            ++$cnt;
            $return .= sprintf(
                $row,
                $cnt,
                $var['time'],
                $var['query'] . $bind . '</span>'
            );
        }

        $this->time = $time;

        return $return;
    }
}

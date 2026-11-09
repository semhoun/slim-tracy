<?php

declare(strict_types=1);

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

namespace SlimTracy\Helpers;

use Tracy\IBarPanel;

final class EloquentORMPanel implements IBarPanel
{
    private readonly int $count;

    private readonly string $parsed;

    private int|float|null $time = null;

    private ?string $icon = null;

    /**
     * @param array<array{time: int|float, query: string, bindings: array<scalar|null>}> $data
     * @param array<string, string> $ver
     */
    public function __construct(array $data = [], private array $ver = [])
    {
        $this->count = count($data);
        $this->parsed = $this->parse($data);
    }

    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/EloquentORMPanel.html');

        return '
        <span title="DB Query Info">' . $this->icon . '
            <span class="tracy-label">' . $this->count . ' / ' . $this->time . '</span> ms
        </span>';
    }

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

    private function getHeader(): string
    {
        return '<thead><tr><th><b>Count</b></th><th><b>Time,&nbsp;ms</b></th><th>Query / Bindings</th></tr></thead>';
    }

    private function getBaseRow(): string
    {
        return '<tr><td>%s</td><td>%s</td><td>%s</td></tr>';
    }

    /** @param array<array{time: int|float, query: string, bindings: array<scalar|null>}> $data */
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

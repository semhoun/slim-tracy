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

final class IncludedFiles implements IBarPanel
{
    private string $icon = '';

    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/IncludedFiles.html');

        return '
        <span title="Included Files">
            ' . $this->icon . '
        </span>';
    }

    public function getPanel(): string
    {
        $files = get_included_files();
        $ret = $this->getHeader();

        foreach ($files as $num => $file) {
            ++$num;
            $ret .= sprintf(
                $this->getBaseRow(),
                $num,
                $file
            );
        }

        return '
        <h1>' . $this->icon . ' &nbsp; Included Files: ' . $num . '</h1>
        <div class="tracy-inner">
            <table width="100%">' . $ret . '</table>
        </div>';
    }

    private function getHeader(): string
    {
        return '<thead><tr><th><b>Count</b></th><th>File</th></tr></thead>';
    }

    private function getBaseRow(): string
    {
        return '<tr><td>%s</td><td>%s</td></tr>';
    }
}

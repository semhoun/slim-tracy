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

/** Lists the PHP files included during the current request. */
final class IncludedFiles implements IBarPanel
{
    /**
     * @var string Included-files icon loaded while rendering the tab.
     */
    private string $icon = '';

    /**
     * Renders the included-files toolbar tab.
     *
     * @return string Toolbar HTML identifying the included-files panel.
     */
    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/IncludedFiles.html');

        return '
        <span title="Included Files">
            ' . $this->icon . '
        </span>';
    }

    /**
     * Lists included file paths in their inclusion order.
     *
     * @return string File table HTML with the total number of included files.
     */
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

    /**
     * Builds the included-files table headings.
     *
     * @return string Table header HTML for sequence numbers and file paths.
     */
    private function getHeader(): string
    {
        return '<thead><tr><th><b>Count</b></th><th>File</th></tr></thead>';
    }

    /**
     * Provides the format used for each included file.
     *
     * @return string HTML format with sequence-number and file-path placeholders.
     */
    private function getBaseRow(): string
    {
        return '<tr><td>%s</td><td>%s</td></tr>';
    }
}

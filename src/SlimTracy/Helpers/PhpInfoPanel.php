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

use DOMDocument;
use Tracy\IBarPanel;

/** Embeds PHP configuration information in the Tracy toolbar. */
final class PhpInfoPanel implements IBarPanel
{
    /**
     * @var string|null PHP icon loaded while rendering the tab.
     */
    private ?string $icon = null;

    /**
     * Renders the PHP information toolbar tab.
     *
     * @return string Toolbar HTML identifying the PHP information panel.
     */
    public function getTab(): string
    {
        $this->icon = file_get_contents(__DIR__ . '/../Templates/Icons/PhpInfoPanel.html');

        return '
        <span title="PHP Info">
            ' . $this->icon . '
        </span>';
    }

    /**
     * Captures PHP configuration output and removes its images.
     *
     * @return string Panel HTML containing the PHP information body.
     */
    public function getPanel(): string
    {
        ob_start();
        phpinfo();
        $phpInfo = ob_get_contents();
        ob_get_clean();

        // warning:
        // DOMDocument::loadHTML(): htmlParseEntityRef: no name in Entity, line: 64
        // suppress warnings
        libxml_use_internal_errors(true);

        $domDocument = new DOMDocument();
        $domDocument->loadHTML($phpInfo);

        $body = $domDocument->getElementsByTagName('body')->item(0);
        $this->removeElementsByTagName('img', $body);
        return (require __DIR__ . '/../Templates/PhpInfoBody.phtml')([
            'icon' => $this->icon,
            'phpInfo' => $domDocument->saveHTML($body),
        ]);
    }

    /**
     * Removes matching descendants from the captured PHP information body.
     *
     * @param string      $tagName  Element name to remove.
     * @param \DOMElement $document Root element whose descendants are searched.
     *
     * @return void Matching descendants are removed from the supplied element in place.
     */
    private function removeElementsByTagName(string $tagName, \DOMElement $document): void
    {
        $nodeList = $document->getElementsByTagName($tagName);
        for ($nodeIdx = $nodeList->length - 1; $nodeIdx >= 0; --$nodeIdx) {
            $node = $nodeList->item($nodeIdx);
            $node->parentNode->removeChild($node);
        }
    }
}

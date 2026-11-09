<?php

/**
 * Copyright 2016 1f7.wizard@gmail.com
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

namespace Tests\SlimTracy\Helpers;

use Tests\BaseTestCase;

/**
 * Class PanelSelectorTest
 * @package Tests\SlimTracy\Helpers
 */
class PanelSelectorTest extends BaseTestCase
{
    public function testPanelSelector()
    {
        $panel = new \SlimTracy\Helpers\PanelSelector(
            [],
            array_diff_key($this->cfg['settings']['tracy'], ['configs' => null])
        );
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);

        // test Tracy tab
        $this->assertMatchesRegularExpression('#Panel Selector#', $panel->getTab());
        // test Tracy panel
        $this->assertMatchesRegularExpression('#Panel Selector#', $panel->getPanel());
    }

    public function testFormatPanelName()
    {
        $words = [
            'showSlimEnvironmentPanel',// 3 words
            'showSlimContainer',// 2 words
            'showXDebugHelper',// 2 upper in word
            'showEloquentORMPanel'// 3 upper in word
        ];
        $panel = new \SlimTracy\Helpers\PanelSelector(
            ['showSlimContainer' => 1],
            array_fill_keys($words, 0)
        );
        $result = $panel->getPanel();
        foreach ([' Slim Environment Panel', ' Slim Container', ' XDebug Helper', ' Eloquent ORM Panel'] as $label) {
            $this->assertStringContainsString('/>&nbsp;' . $label, $result);
        }
        $this->assertStringContainsString('checked="checked" value="showSlimContainer"', $result);
        $this->assertSame($result, $panel->getPanel());
    }
}

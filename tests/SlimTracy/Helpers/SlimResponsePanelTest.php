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
 * Class SlimResponsePanelTest
 * @package Tests\SlimTracy\Helpers
 */
class SlimResponsePanelTest extends BaseTestCase
{
    /**
     * Render a real response through Tracy's public static dumper API.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testSlimResponsePanel()
    {
        $app = $this->createApp();
        $this->assertInstanceOf('\Slim\App', $app);

        $v = [
            'slim' => \Slim\App::VERSION,
        ];

        $panel = new \SlimTracy\Helpers\SlimResponsePanel(
            \Tracy\Dumper::toHtml(new \Nyholm\Psr7\Response()),
            $v
        );
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);

        // test Tracy tab
        $this->assertMatchesRegularExpression('#Slim Http Response#', $panel->getTab());
        // test Tracy panel
        $this->assertMatchesRegularExpression('#Response:#', $panel->getPanel());
    }
}

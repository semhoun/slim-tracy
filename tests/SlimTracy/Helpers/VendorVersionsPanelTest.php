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

use SlimTracy\Helpers\ComposerDocument;
use Tests\BaseTestCase;

/**
 * Class VendorVersionsPanelTest
 * @package Tests\SlimTracy\Helpers
 */
class VendorVersionsPanelTest extends BaseTestCase
{
    public function testVendorVersionsPanel()
    {
        $panel = new \SlimTracy\Helpers\VendorVersionsPanel();
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);

        // test Tracy tab
        $this->assertMatchesRegularExpression('/Vendor Versions/s', $panel->getTab());
        // test Tracy panel
        $this->assertMatchesRegularExpression('/VendorVersionsPanel/s', $panel->getPanel());
        unset($panel);
    }

    public function testVendorVersionsPanelWithData()
    {
        $path = realpath(__DIR__ . '/../../../');

        $panel = new \SlimTracy\Helpers\VendorVersionsPanel($path);
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);

        $this->assertMatchesRegularExpression('/phpunit\/php-code-coverage/s', $panel->getPanel());
        $this->assertSame($panel->getPanel(), $panel->getPanel());
        $this->assertFalse(function_exists('h'));
    }

    public function testVendorVersionsPanelComposerDirectory()
    {
        // normal path, error empty
        $path = realpath(__DIR__ . '/../../../');
        $panel = new \SlimTracy\Helpers\VendorVersionsPanel($path);
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);
        $this->assertEmpty($panel->getError());

        // fake path
        $panel = new \SlimTracy\Helpers\VendorVersionsPanel('/fakePath/');
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);
        $this->assertEquals('Path "/fakePath/" is not a directory.', $panel->getError());

        // path without composer
        $path = realpath(__DIR__ . '/../../');
        $panel = new \SlimTracy\Helpers\VendorVersionsPanel($path);
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);
        $this->assertStringEndsWith('does not contain the composer.lock file.', $panel->getError());
    }

    public function testVendorVersionsPanelDecode()
    {
        // normal file
        $path = realpath(__DIR__ . '/../../../tests/');
        $jsonFile = $path . DIRECTORY_SEPARATOR . 'testComposer.json';
        $lockFile = $path . DIRECTORY_SEPARATOR . 'testComposer.lock';

        $result = ComposerDocument::read($jsonFile)['data'];
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('keywords', $result);
        $this->assertIsArray($result['keywords']);

        $result = ComposerDocument::read($lockFile)['data'];
        $this->assertArrayHasKey('_readme', $result);
        $this->assertArrayHasKey('packages', $result);
        $this->assertIsArray($result['packages']);

        // fake file with Syntax error
        $path = realpath(__DIR__ . '/../../../tests/');
        $jsonFile = $path . DIRECTORY_SEPARATOR . 'testFake.json';
        $result = ComposerDocument::read($jsonFile);
        $this->assertSame([], $result['data']);
        $this->assertStringStartsWith('Syntax error', $result['error']);
    }

    public function testVendorVersionsPanelDecodeWarning()
    {
        // no file
        $path = realpath(__DIR__ . '/../../../tests/');
        $jsonFile = $path . DIRECTORY_SEPARATOR . 'zzzWWW.json';

        $result = ComposerDocument::read($jsonFile);
        $this->assertSame(['data' => [], 'error' => null], $result);
    }
}

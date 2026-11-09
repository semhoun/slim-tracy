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
 * Class EloquentORMPanelTest
 * @package Tests\SlimTracy\Helpers
 */
class EloquentORMPanelTest extends BaseTestCase
{
    public function testEloquentORMPanel()
    {
        $capsule = new \Illuminate\Database\Capsule\Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $capsule::connection()->enableQueryLog();
        $capsule::connection()->select('SELECT 42 AS answer');
        $this->assertCount(1, $capsule::connection()->getQueryLog());

        $this->assertInstanceOf('\Illuminate\Database\Capsule\Manager', $capsule);

        $panel = new \SlimTracy\Helpers\EloquentORMPanel(
            $capsule::connection()->getQueryLog(),
            ['slim' => \Slim\App::VERSION]
        );
        $this->assertInstanceOf('\Tracy\IBarPanel', $panel);
        $this->assertMatchesRegularExpression('#DB Query Info#', $panel->getTab());
        $this->assertMatchesRegularExpression('#Eloquent ORM#', $panel->getPanel());
        $this->assertStringContainsString('SELECT 42 AS answer', $panel->getPanel());
        $this->assertSame($panel->getTab(), $panel->getTab());
    }

    public function testEloquentORMPanelParser()
    {
        $data =
            [
                'query' => 'update `mybb_sessions` set `uid` = ?, `time` = ?, `location` = ?,
                    `useragent` = ?, `location1` = ?, `location2` = ?, `nopermission` = ? where `sid` = ?',
                'bindings' =>
                    [
                        0 => 1,
                        1 => 1482512713,
                        2 => '/?',
                        3 => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:50.0) Gecko/20100101 Firefox/50.0',
                        4 => 0,
                        5 => 0,
                        6 => 0,
                        7 => '89a412a4ad6ad9df88b42ca4c12bb271',
                    ],
                    'time' => 4.9100000000000001,
            ];
        // getHeader()
        $headerRow = $this->callProtectedMethod('getHeader', '\SlimTracy\Helpers\EloquentORMPanel', [], [[]]);
        // <thead><tr><th><b>Count</b></th><th><b>Time,&nbsp;ms</b></th><th>Query / Bindings</th></tr></thead>
        $this->assertMatchesRegularExpression('#<thead><tr><th>#', $headerRow);

        // getBaseRow()
        $baseRow = $this->callProtectedMethod('getBaseRow', '\SlimTracy\Helpers\EloquentORMPanel', [], [[]]);
        // <tr><td>%s</td><td>%s</td><td>%s</td></tr>
        $this->assertMatchesRegularExpression('#<tr><td>#', $baseRow);

        // parse($data)
        $result = $this->callProtectedMethod('parse', '\SlimTracy\Helpers\EloquentORMPanel', [[0 => $data]], [[]]);
        $this->assertMatchesRegularExpression('#89a412a4ad6ad9df88b42ca4c12bb271#', $result);
    }
}

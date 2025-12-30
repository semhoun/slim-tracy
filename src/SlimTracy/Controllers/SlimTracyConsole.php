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

namespace SlimTracy\Controllers;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use SlimTracy\Helpers\Console\WebConsoleRPCServer;

class SlimTracyConsole extends WebConsoleRPCServer
{
    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    public function index(Request $request, Response $response): Response
    {
        $cfg = $this->container->get('tracy.settings')['configs'];

        $this->noLogin = $cfg['ConsoleNoLogin'] ?: false;
        foreach ($cfg['ConsoleAccounts'] as $u => $p) {
            $this->accounts[$u] = $p;
        }

        $this->passwordHashAlgorithm = $cfg['ConsoleHashAlgorithm'] ?: '';
        $this->homeDirectory = $cfg['ConsoleHomeDirectory'] ?: '';

        $ConsoleResponse = $this->execute();

        if ($cfg['ConsoleFromEncoding'] && $cfg['ConsoleFromEncoding'] !== 'UTF-8') {
            $ConsoleResponse['result']['output'] = mb_convert_encoding(
                $ConsoleResponse['result']['output'],
                'UTF-8',
                $cfg['ConsoleFromEncoding']
            );
        }

        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(
            (string) json_encode(
                $ConsoleResponse,
                JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
            )
        );
        return $response;
    }
}

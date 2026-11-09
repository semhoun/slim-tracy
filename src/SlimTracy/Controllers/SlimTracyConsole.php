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
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimTracy\Controllers\Support\ConsoleOutput;
use SlimTracy\Controllers\Support\ConsoleTransport;

/** Adapts an explicit PSR-7 request to the console's JSON-RPC transport. */
final readonly class SlimTracyConsole
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $config = $this->container->get('tracy.settings')['configs'];
        $result = ConsoleTransport::execute($request, $config);
        $output = ConsoleOutput::convert($result, (string) ($config['ConsoleFromEncoding'] ?? ''));
        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        $response = $response->withHeader('Access-Control-Allow-Headers', 'x-requested-with, content-type');
        $response->getBody()->write(
            (string) json_encode($output, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)
        );
        return $response;
    }
}

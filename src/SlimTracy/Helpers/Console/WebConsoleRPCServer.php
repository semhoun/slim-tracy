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

namespace SlimTracy\Helpers\Console;

/** Public console commands, independently usable and exposable through JSON-RPC. */
final readonly class WebConsoleRPCServer
{
    /** Authentication policy. */
    private ConsoleAuthentication $authentication;

    /** Session directory management. */
    private ConsoleWorkingDirectory $directory;

    /** RPC transport-independent facade. */
    private BaseJsonRpcServer $server;

    /**
     * @param array<string, string> $accounts
     * @param string|array<string, string> $homeDirectory
     */
    public function __construct(
        array $accounts = [],
        bool $noLogin = false,
        string $passwordHashAlgorithm = '',
        string|array $homeDirectory = '',
        int $maxBatchCalls = 10
    ) {
        $this->authentication = new ConsoleAuthentication($accounts, $noLogin, $passwordHashAlgorithm);
        $this->directory = new ConsoleWorkingDirectory($homeDirectory);
        $this->server = new BaseJsonRpcServer($this, $maxBatchCalls);
    }

    /** @return array<string|int, array|bool|float|int|\stdClass|string|null> */
    public function execute(
        string $input = '',
        string $requestMethod = 'POST',
        bool $serviceMap = false,
        string $target = ''
    ): array {
        return $this->server->execute($input, $requestMethod, $serviceMap, $target);
    }

    /** @return array{token: string, environment: array<string, string|false>, output?: string} */
    public function login(string $user, string $password): array
    {
        $result = ['token' => $this->authentication->login($user, $password),
            'environment' => $this->directory->environment(),
        ];
        $home = $this->directory->home(trim($user));
        if (is_dir($home)) {
            $result['environment']['path'] = $home;
        } else {
            $result['output'] = 'Home directory not found: ' . $home;
        }

        return $result;
    }

    /**
     * @param array<string, array|bool|float|int|\stdClass|string|null>|\stdClass|null $environment
     *
     * @return array<string, array|string>
     */
    public function cd(string $token, array|\stdClass|null $environment, string $path): array
    {
        $user = $this->authentication->user($token);
        return $this->directory->restore($environment, $user) ?? $this->directory->change($path, $user);
    }

    /**
     * @param array<string, array|bool|float|int|\stdClass|string|null>|\stdClass|null $environment
     *
     * @return array<string, array|string>
     */
    public function completion(string $token, array|\stdClass|null $environment, string $pattern): array
    {
        $user = $this->authentication->user($token);
        return $this->directory->restore($environment, $user) ?? (new ConsoleCompletion())->complete($pattern);
    }

    /**
     * @param array<string, array|bool|float|int|\stdClass|string|null>|\stdClass|null $environment
     *
     * @return array<string, array|string>
     */
    public function run(string $token, array|\stdClass|null $environment, string $command): array
    {
        $user = $this->authentication->user($token);
        return $this->directory->restore($environment, $user) ?? ['output' => (new ConsoleCommand())->run($command)];
    }
}

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

namespace Tests\SlimTracy\Helpers\Console;

use PHPUnit\Framework\TestCase;
use SlimTracy\Exceptions\IncorrectUserOrPassword;
use SlimTracy\Helpers\Console\ConsoleAuthentication;
use SlimTracy\Helpers\Console\ConsoleWorkingDirectory;
use SlimTracy\Helpers\Console\WebConsoleRPCServer;

final class WebConsoleRPCServerTest extends TestCase
{
    public function testAuthenticationAndLegacyTokenFormat(): void
    {
        $auth = new ConsoleAuthentication(['dev' => sha1('dev')], passwordHashAlgorithm: 'sha1');
        $token = $auth->login(' dev ', ' dev ');
        self::assertSame('dev:358e86472ec7619731baf6950db699b26e2c2df8d51a31159d40ae4987f6fbab', $token);
        self::assertSame('dev', $auth->user($token));
        self::assertSame('', (new ConsoleAuthentication(noLogin: true))->user('not-a-token'));
        $this->expectException(IncorrectUserOrPassword::class);
        $auth->user('dev:358e8');
    }

    public function testWrongPasswordIsRejected(): void
    {
        $auth = new ConsoleAuthentication(['dev' => sha1('dev')], passwordHashAlgorithm: 'sha1');
        $this->expectException(IncorrectUserOrPassword::class);
        $auth->login('dev', 'zzz');
    }

    public function testEmptyPasswordIsRejectedEvenIfItsHashWasConfigured(): void
    {
        $auth = new ConsoleAuthentication(['dev' => sha1('')], passwordHashAlgorithm: 'sha1');
        $this->expectException(IncorrectUserOrPassword::class);
        $auth->login('dev', '');
    }

    public function testUnauthenticatedCommandsAreRejected(): void
    {
        $server = new WebConsoleRPCServer(['dev' => 'secret']);
        foreach (['run', 'cd', 'completion'] as $method) {
            $response = $server->execute(json_encode([
                'jsonrpc' => '2.0', 'method' => $method, 'params' => ['invalid', [], ''], 'id' => 1,
            ], JSON_THROW_ON_ERROR));
            self::assertArrayHasKey('error', $response);
            self::assertArrayNotHasKey('result', $response);
        }
    }

    public function testLoginAndPerUserHomeDirectories(): void
    {
        $server = new WebConsoleRPCServer(
            ['alice' => 'secret', 'bob' => 'different'],
            homeDirectory: ['alice' => __DIR__, 'bob' => dirname(__DIR__)]
        );
        $alice = $server->login('alice', 'secret');
        $bob = $server->login('bob', 'different');
        self::assertSame(__DIR__, $alice['environment']['path']);
        self::assertSame(dirname(__DIR__), $bob['environment']['path']);
        self::assertArrayHasKey('hostname', $alice['environment']);
        self::assertSame('alice:' . hash('sha256', 'secret'), $alice['token']);
        self::assertSame(__DIR__, $server->login('alice', 'secret')['environment']['path']);
        $missing = (new WebConsoleRPCServer(['dev' => 'dev'], homeDirectory: '/no-such-console-directory'))
            ->login('dev', 'dev');
        self::assertSame('Home directory not found: /no-such-console-directory', $missing['output']);
    }

    public function testWorkingDirectoryRestorationAndChange(): void
    {
        $workingDirectory = getcwd();
        $directory = new ConsoleWorkingDirectory(['dev' => __DIR__]);
        try {
            self::assertNull($directory->restore(null, 'dev'));
            self::assertSame(__DIR__, getcwd());
            self::assertNull($directory->restore((object) ['path' => dirname(__DIR__)], 'dev'));
            self::assertSame(dirname(__DIR__), getcwd());
            $failure = $directory->restore(['path' => '/no-such-console-directory'], 'dev');
            self::assertStringContainsString('Current working directory not found', $failure['output']);
            self::assertSame(getcwd(), $failure['environment']['path']);
            self::assertNull($directory->restore(['path' => []], 'dev'));
            self::assertSame(__DIR__, getcwd());
            self::assertSame(__DIR__, $directory->change('', 'dev')['environment']['path']);
            self::assertSame('cd: /no-such-console-directory: No such directory',
                $directory->change('/no-such-console-directory', 'dev')['output']);
        } finally {
            chdir($workingDirectory);
        }
    }

    public function testCompletionWithExplicitDirectoryCanBeRepeated(): void
    {
        $server = new WebConsoleRPCServer(noLogin: true, homeDirectory: __DIR__);
        $entries = array_values(array_diff(scandir(__DIR__), ['.', '..']));
        natsort($entries);
        $expected = array_values(array_map(static fn (string $entry): string => __DIR__ . '/' . $entry, $entries));
        $workingDirectory = getcwd();
        try {
            foreach ([__DIR__, __DIR__ . '/'] as $directory) {
                $completion = $server->completion('', [], $directory);
                self::assertSame(['completion' => $expected], $completion);
                self::assertSame($completion, $server->completion('', [], $directory));
            }

            self::assertSame(['completion' => array_values($entries)], $server->completion('', [], 'BaseJson'));
            self::assertSame(['completion' => []], $server->completion('', [], '/no-such-dir/child'));
        } finally {
            chdir($workingDirectory);
        }
    }

    public function testCommandsAndCd(): void
    {
        $server = new WebConsoleRPCServer(['dev' => 'secret'], homeDirectory: __DIR__);
        $login = $server->login('dev', 'secret');
        $workingDirectory = getcwd();
        try {
            self::assertSame(['output' => ''], $server->run($login['token'], $login['environment'], ''));
            self::assertSame(['output' => 'HelloWorld!!!'], $server->run(
                $login['token'], $login['environment'], 'printf "HelloWorld!!!\n"'
            ));
            self::assertSame(['output' => 'stderr'], $server->run(
                $login['token'], $login['environment'], 'printf "stderr\n" >&2'
            ));
            self::assertStringContainsString('not found', $server->run(
                $login['token'], $login['environment'], 'no-such-console-command'
            )['output']);
            $cd = $server->cd($login['token'], $login['environment'], '../');
            self::assertSame(dirname(__DIR__), $cd['environment']['path']);
            self::assertSame(__DIR__, $server->cd($login['token'], $login['environment'], '')['environment']['path']);
            $error = $server->run($login['token'], ['path' => '/no-such-console-directory'], 'exit 1');
            self::assertStringContainsString('Current working directory not found', $error['output']);
            self::assertArrayHasKey('environment', $error);
        } finally {
            chdir($workingDirectory);
        }
    }

    public function testJsonRpcNamedLoginAndPublicDiscovery(): void
    {
        $server = new WebConsoleRPCServer(['named-user' => sha1('different-password')],
            passwordHashAlgorithm: 'sha1', homeDirectory: __DIR__);
        $response = $server->execute('{"jsonrpc":"2.0","method":"login",'
            . '"params":{"password":"different-password","user":"named-user"},"id":3}');
        self::assertSame(3, $response['id']);
        self::assertSame($server->login('named-user', 'different-password')['token'], $response['result']['token']);
        $map = $server->execute(serviceMap: true, target: '/console');
        self::assertSame(['login', 'cd', 'completion', 'run'], array_keys($map['services']));
        self::assertSame('/console', $map['target']);
    }

    public function testConsoleDoesNotExposeInfrastructure(): void
    {
        $server = new WebConsoleRPCServer(['named-user' => 'secret-password-hash']);
        foreach (['getAccountPassword', 'getHash', 'execute', '__construct'] as $method) {
            $response = $server->execute(json_encode([
                'jsonrpc' => '2.0', 'method' => $method, 'params' => [], 'id' => 1,
            ], JSON_THROW_ON_ERROR));
            self::assertSame(-32601, $response['error']['code'], $method);
            self::assertArrayNotHasKey('result', $response, $method);
        }
    }
}

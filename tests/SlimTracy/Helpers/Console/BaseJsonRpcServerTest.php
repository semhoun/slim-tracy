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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SlimTracy\Helpers\Console\BaseJsonRpcServer;
use SlimTracy\Helpers\Console\RpcMethod;

final class BaseJsonRpcServerTest extends TestCase
{
    #[DataProvider('invalidRequests')]
    public function testInvalidRequestsReturnJsonRpcErrors(string $request, int $code): void
    {
        $response = (new BaseJsonRpcServer())->execute($request);
        self::assertSame('2.0', $response['jsonrpc']);
        self::assertSame($code, $response['error']['code']);
        self::assertNull($response['id']);
    }

    public static function invalidRequests(): array
    {
        return [
            'malformed' => ['{', -32700],
            'empty input' => ['', -32700],
            'empty batch' => ['[]', -32600],
            'null' => ['null', -32600],
            'number' => ['42', -32600],
            'string' => ['"request"', -32600],
            'boolean' => ['true', -32600],
            'missing version' => ['{}', -32600],
            'missing method' => ['{"jsonrpc":"2.0"}', -32601],
            'invalid method type' => ['{"jsonrpc":"2.0","method":[]}', -32601],
        ];
    }

    public function testTransportMetadataIsExplicit(): void
    {
        $server = new BaseJsonRpcServer();
        self::assertSame(-32600, $server->execute('{}', 'GET')['error']['code']);
        $map = $server->execute(serviceMap: true, target: '/console');
        self::assertSame('POST', $map['transport']);
        self::assertSame('JSON-RPC-2.0', $map['envelope']);
        self::assertSame('2.0', $map['SMDVersion']);
        self::assertSame('application/json', $map['contentType']);
        self::assertSame('/console', $map['target']);
        self::assertSame([], $map['services']);
        self::assertSame('', $map['description']);
    }

    public function testInvalidBatchConfigurationIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BaseJsonRpcServer(maxBatchCalls: 0);
    }

    public function testRegistersNamespacedServicesWithoutDynamicProperties(): void
    {
        $service = new RpcTestService();
        $server = new BaseJsonRpcServer();
        self::assertSame($server, $server->registerInstance($service, 'math'));
        self::assertSame(['math' => $service], $server->getInstances());
        $response = $server->execute('{"jsonrpc":"2.0","method":"math.add","params":[2,3],"id":0}');
        self::assertSame(['jsonrpc' => '2.0', 'result' => 5, 'id' => 0], $response);
        $services = $server->execute(serviceMap: true)['services'];
        self::assertArrayHasKey('math.add', $services);
        self::assertArrayNotHasKey('math.secret', $services);
        self::assertArrayNotHasKey('math.protectedSecret', $services);
        self::assertSame('right', $services['math.add']['parameters'][1]['name']);
        self::assertSame(1, $services['math.add']['parameters'][1]['default']);
        self::assertSame('int', $services['math.add']['parameters'][0]['type']);
        self::assertSame('sum', $services['math.add']['returns']['description']);
        self::assertStringContainsString('Example public service', $server->execute(serviceMap: true)['description']);
    }

    #[DataProvider('numericNamespaces')]
    public function testServiceMapAndInvocationAcceptNumericNamespaces(string $namespace): void
    {
        $server = new BaseJsonRpcServer();
        $server->registerInstance(new RpcTestService(), $namespace);
        $services = $server->execute(serviceMap: true)['services'];
        self::assertArrayHasKey($namespace . '.add', $services);
        self::assertArrayNotHasKey($namespace . '.secret', $services);
        $response = $server->execute(json_encode([
            'jsonrpc' => '2.0', 'method' => $namespace . '.add', 'params' => [2, 3], 'id' => 1,
        ], JSON_THROW_ON_ERROR));
        self::assertSame(5, $response['result']);
    }

    public static function numericNamespaces(): array
    {
        return ['zero' => ['0'], 'numeric string' => ['123']];
    }

    public function testBatchesNotificationsAndConsecutiveRequestsHaveNoSharedState(): void
    {
        $service = new RpcTestService();
        $server = new BaseJsonRpcServer($service, maxBatchCalls: 2);
        $response = $server->execute('[{"jsonrpc":"2.0","method":"add","params":[2],"id":1},'
            . '{"jsonrpc":"2.0","method":"add","params":[4]}]');
        self::assertSame([['jsonrpc' => '2.0', 'result' => 3, 'id' => 1]], $response);
        self::assertSame([], $server->execute('{"jsonrpc":"2.0","method":"add","params":[4]}'));
        self::assertSame([], $server->execute('[{"jsonrpc":"2.0","method":"add","params":[4]}]'));
        self::assertSame(-32600, $server->execute('[{},{},{}]')['error']['code']);
        self::assertSame('2.0', $server->execute('{')['jsonrpc']);
        self::assertSame(8, $server->execute('{"jsonrpc":"2.0","method":"add","params":[7],"id":2}')['result']);
        self::assertSame(5, $service->calls);
    }

    #[DataProvider('parameterCalls')]
    public function testParameters(string $params, ?int $errorCode, int|string $expected): void
    {
        $response = (new BaseJsonRpcServer(new RpcTestService()))->execute(
            '{"jsonrpc":"2.0","method":"add","params":' . $params . ',"id":1}'
        );
        if ($errorCode === null) {
            self::assertSame($expected, $response['result']);
            return;
        }

        self::assertSame($errorCode, $response['error']['code']);
        self::assertStringContainsString((string) $expected, $response['error']['data']);
    }

    public static function parameterCalls(): array
    {
        return [
            'positional' => ['[2,3]', null, 5],
            'optional positional' => ['[2]', null, 3],
            'named reversed' => ['{"right":4,"left":2}', null, 6],
            'optional named' => ['{"left":2}', null, 3],
            'missing positional' => ['[]', -32602, 'Check numbers of required params'],
            'missing named' => ['{"right":2}', -32602, 'left not found'],
            'null' => ['null', -32602, 'Empty required params'],
            'scalar' => ['"invalid"', -32602, 'Cast of params error'],
            'wrong type' => ['[[]]', -32602, 'must be of type int'],
            'unknown named' => ['{"left":2,"unknown":1}', -32602, 'Unknown named parameter'],
        ];
    }

    public function testCompatibilityVersionAndServiceExceptions(): void
    {
        $server = new BaseJsonRpcServer(new RpcTestService());
        self::assertSame(3, $server->execute(
            '{"version":"json-rpc-2.0","method":"add","params":[2],"id":1}'
        )['result']);
        $response = $server->execute('{"jsonrpc":"2.0","method":"fail","id":1}');
        self::assertSame(-32603, $response['error']['code']);
        self::assertSame('application error', $response['error']['data']);
    }

    public function testRpcCannotInvokePrivateOrProtectedMethods(): void
    {
        $server = new BaseJsonRpcServer(new RpcTestService());
        foreach (['secret', 'protectedSecret', 'execute', '__construct'] as $method) {
            $response = $server->execute(json_encode([
                'jsonrpc' => '2.0', 'method' => $method, 'params' => [], 'id' => 1,
            ], JSON_THROW_ON_ERROR));
            self::assertSame(-32601, $response['error']['code'], $method);
            self::assertArrayNotHasKey('result', $response, $method);
        }
    }

    #[DataProvider('unexposableMethods')]
    public function testInvocationHandlesRejectNonPublicAndInfrastructureMethods(string $class, string $method): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(-32601);
        new RpcMethod(new $class(), new \ReflectionMethod($class, $method));
    }

    public static function unexposableMethods(): array
    {
        return [
            'private' => [RpcTestService::class, 'secret'],
            'protected' => [RpcTestService::class, 'protectedSecret'],
            'execution entrypoint' => [BaseJsonRpcServer::class, 'execute'],
            'registry mutation' => [BaseJsonRpcServer::class, 'registerInstance'],
        ];
    }

    public function testPublicInvocationHandleRetainsParameterValidation(): void
    {
        $service = new RpcTestService();
        $method = new RpcMethod($service, new \ReflectionMethod($service, 'add'));
        self::assertSame('Empty required params', $method->validateParameters(null));
        self::assertNull($method->validateParameters([2, 3]));
        self::assertSame(['result' => 5], $method->invoke([2, 3]));
        self::assertSame(1, $service->calls);
    }
}

/** Example public service. */
final class RpcTestService
{
    public int $calls = 0;

    /**
     * Add two integers.
     * @param int $left first operand
     * @param int $right second operand
     * @return int sum
     */
    public function add(int $left, int $right = 1): int
    {
        ++$this->calls;
        return $left + $right;
    }

    public function fail(): never
    {
        throw new \RuntimeException('application error', -32603);
    }

    protected function protectedSecret(): string
    {
        return 'protected password';
    }

    private function secret(): string
    {
        return 'private password';
    }
}

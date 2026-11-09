<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Helpers\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SlimTracy\Helpers\Console\BaseJsonRpcServer;

/** Verifies service registration, discovery, parameter binding, and service errors. */
final class BaseJsonRpcServiceTest extends TestCase
{
    /** Verify namespaced discovery exposes only public service methods. */
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

    /** Verify numeric namespaces work for both discovery and execution. */
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

    /** Supply zero and nonzero numeric namespaces. */
    public static function numericNamespaces(): array
    {
        return ['zero' => ['0'], 'numeric string' => ['123']];
    }

    /** Verify positional and named parameters preserve validation and defaults. */
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

    /** Supply valid parameters, missing operands, invalid types, and unknown names. */
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

    /** Verify the compatibility version key and application-error envelope. */
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
}

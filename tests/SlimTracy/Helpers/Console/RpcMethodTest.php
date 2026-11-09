<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Helpers\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SlimTracy\Helpers\Console\BaseJsonRpcServer;
use SlimTracy\Helpers\Console\RpcMethod;

/** Verifies invocation handles enforce service visibility and parameter validation. */
final class RpcMethodTest extends TestCase
{
    /** Verify RPC calls cannot invoke private, protected, or infrastructure methods. */
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

    /** Verify a reflected non-service method cannot become an invocation handle. */
    #[DataProvider('unexposableMethods')]
    public function testInvocationHandlesRejectNonPublicAndInfrastructureMethods(string $class, string $method): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(-32601);
        new RpcMethod(new $class(), new ReflectionMethod($class, $method));
    }

    /** Supply methods which must never be exposed as RPC services. */
    public static function unexposableMethods(): array
    {
        return [
            'private' => [RpcTestService::class, 'secret'],
            'protected' => [RpcTestService::class, 'protectedSecret'],
            'execution entrypoint' => [BaseJsonRpcServer::class, 'execute'],
            'registry mutation' => [BaseJsonRpcServer::class, 'registerInstance'],
        ];
    }

    /** Verify public invocation handles retain validation and call the service once. */
    public function testPublicInvocationHandleRetainsParameterValidation(): void
    {
        $service = new RpcTestService();
        $method = new RpcMethod($service, new ReflectionMethod($service, 'add'));
        self::assertSame('Empty required params', $method->validateParameters(null));
        self::assertNull($method->validateParameters([2, 3]));
        self::assertSame(['result' => 5], $method->invoke([2, 3]));
        self::assertSame(1, $service->calls);
    }
}

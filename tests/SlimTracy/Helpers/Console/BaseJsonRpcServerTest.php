<?php

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

declare(strict_types=1);

namespace Tests\SlimTracy\Helpers\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SlimTracy\Helpers\Console\BaseJsonRpcServer;

/** Verifies JSON-RPC envelopes, transport metadata, and batch request isolation. */
final class BaseJsonRpcServerTest extends TestCase
{
    /** Verify malformed requests return the expected JSON-RPC error envelope. */
    #[DataProvider('invalidRequests')]
    public function testInvalidRequestsReturnJsonRpcErrors(string $request, int $code): void
    {
        $response = (new BaseJsonRpcServer())->execute($request);
        self::assertSame('2.0', $response['jsonrpc']);
        self::assertSame($code, $response['error']['code']);
        self::assertNull($response['id']);
    }

    /** Supply malformed JSON and invalid JSON-RPC envelopes. */
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

    /** Verify discovery reports its explicit HTTP and JSON-RPC transport metadata. */
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

    /** Reject a nonpositive maximum batch size. */
    public function testInvalidBatchConfigurationIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BaseJsonRpcServer(maxBatchCalls: 0);
    }

    /** Verify notifications and subsequent requests never retain previous responses. */
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
}

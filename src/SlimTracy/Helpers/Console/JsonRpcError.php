<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** JSON-RPC error envelopes shared by parsing and invocation. */
final class JsonRpcError
{
    private const MESSAGES = [
        -32700 => 'Parse error',
        -32600 => 'Invalid Request',
        -32601 => 'Method not found',
        -32602 => 'Invalid params',
        -32603 => 'Internal error',
    ];

    /**
     * Build an error envelope, preserving the public JSON-RPC id parameter name.
     *
     * @return array<string, array|bool|float|int|\stdClass|string|null>
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public static function response(
        int $code,
        array|bool|float|int|\stdClass|string|null $id = null,
        array|bool|float|int|\stdClass|string|null $data = null
    ): array {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => self::MESSAGES[$code] ?? 'Internal error', 'data' => $data],
        ];
    }
}

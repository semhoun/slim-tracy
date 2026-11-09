<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Validate protocol envelopes and invoke resolved services. */
final readonly class JsonRpcDispatcher
{
    /** @param array<array-key, object> $instances */
    public function __construct(private array $instances)
    {
    }

    /** @return array<string, array|bool|float|int|\stdClass|string|null>|null */
    public function dispatch(array|bool|float|int|\stdClass|string|null $call): ?array
    {
        if (! $call instanceof \stdClass) {
            return JsonRpcError::response(-32600);
        }

        $version = $call->jsonrpc ?? $call->version ?? null;
        if (! in_array($version, ['2.0', 'json-rpc-2.0'], true)) {
            return JsonRpcError::response(-32600, $call->id ?? null);
        }

        $name = $call->method ?? null;
        $resolved = is_string($name) ? (new RpcMethodRegistry($this->instances))->resolve($name) : null;
        if (! $resolved instanceof RpcMethod) {
            return JsonRpcError::response(-32601, $call->id ?? null);
        }

        return (new JsonRpcInvocation())->invoke($call, $resolved);
    }
}

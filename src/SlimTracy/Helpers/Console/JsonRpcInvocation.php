<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Invoke a validated public service method and construct its result or error envelope. */
final class JsonRpcInvocation
{
    /**
     * Construct errors through the shared stateless JSON-RPC envelope factory.
     *
     * @return array<string, array|bool|float|int|\stdClass|string|null>|null
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function invoke(\stdClass $call, RpcMethod $method): ?array
    {
        $params = $call->params ?? null;
        $error = $method->validateParameters($params);
        if ($error !== null) {
            return JsonRpcError::response(-32602, $call->id ?? null, $error);
        }

        try {
            $result = $method->invoke((array) $params);
        } catch (\TypeError $error) {
            return JsonRpcError::response(-32602, $call->id ?? null, $error->getMessage());
        } catch (\Throwable $error) {
            return JsonRpcError::response($error->getCode(), $call->id ?? null, $error->getMessage());
        }

        return isset($call->id) ? ['jsonrpc' => '2.0', 'result' => $result['result'], 'id' => $call->id] : null;
    }
}

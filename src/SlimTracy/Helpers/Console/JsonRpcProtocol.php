<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Parse and collect single calls, batches and notifications without retaining request state. */
final readonly class JsonRpcProtocol
{
    /** @param array<array-key, object> $instances */
    public function __construct(private array $instances, private int $maxBatchCalls)
    {
    }

    /**
     * Construct protocol errors through the shared stateless envelope factory.
     *
     * @return array<string|int, array|bool|float|int|\stdClass|string|null>
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function execute(string $input, string $requestMethod): array
    {
        if ($requestMethod !== 'POST') {
            return JsonRpcError::response(-32600);
        }

        try {
            $request = json_decode($input, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return JsonRpcError::response(-32700);
        }

        $dispatcher = new JsonRpcDispatcher($this->instances);
        if (! is_array($request)) {
            return $dispatcher->dispatch($request) ?? [];
        }

        return (new JsonRpcBatch($this->maxBatchCalls))->execute($dispatcher, $request);
    }
}

<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Enforce batch limits and collect only the calls requiring responses. */
final readonly class JsonRpcBatch
{
    /** Configure the maximum number of calls in a single request. */
    public function __construct(private int $maxBatchCalls)
    {
    }

    /**
     * @param list<array|bool|float|int|\stdClass|string|null> $calls Decoded calls in the request batch.
     *
     * @return array<string|int, array|bool|float|int|\stdClass|string|null>
     *
     * Invalid batches use the shared stateless JSON-RPC error factory.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function execute(JsonRpcDispatcher $dispatcher, array $calls): array
    {
        if ($calls === [] || count($calls) > $this->maxBatchCalls) {
            return JsonRpcError::response(-32600);
        }

        return array_values(array_filter(array_map($dispatcher->dispatch(...), $calls)));
    }
}

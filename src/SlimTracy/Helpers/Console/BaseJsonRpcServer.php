<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/**
 * JSON RPC Server with explicit transport input.
 *
 * @see http://www.jsonrpc.org/specification
 *
 * @author Sergeyfast
 */
final class BaseJsonRpcServer
{
    /** @var array<array-key, object> */
    private array $instances = [];

    /** Initialize the service registry and protocol limits. */
    public function __construct(?object $instance = null, private readonly int $maxBatchCalls = 10)
    {
        if ($this->maxBatchCalls < 1) {
            throw new \InvalidArgumentException('The JSON-RPC batch limit must be positive.');
        }

        if ($instance !== null) {
            $this->registerInstance($instance);
        }
    }

    /** Register an explicitly exposed service. */
    public function registerInstance(object $instance, string $namespace = ''): self
    {
        $this->instances[$namespace] = $instance;
        return $this;
    }

    /** @return array<array-key, object> */
    public function getInstances(): array
    {
        return $this->instances;
    }

    /** @return array<string|int, array|bool|float|int|\stdClass|string|null> */
    public function execute(
        string $input = '',
        string $requestMethod = 'POST',
        bool $serviceMap = false,
        string $target = ''
    ): array {
        if ($serviceMap) {
            return (new ServiceMap())->describe($this->instances, $target);
        }

        return (new JsonRpcProtocol($this->instances, $this->maxBatchCalls))->execute($input, $requestMethod);
    }
}

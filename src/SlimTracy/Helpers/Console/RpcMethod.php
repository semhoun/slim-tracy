<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** A public, non-infrastructure service method authorized for JSON-RPC invocation. */
final readonly class RpcMethod
{
    /** Reject reflection handles that bypass the service registry's visibility boundary. */
    public function __construct(private object $instance, private \ReflectionMethod $method)
    {
        if (! $method->isPublic() || RpcMethodRegistry::isHidden($method->getName())) {
            throw new \InvalidArgumentException('Method not found', -32601);
        }
    }

    /** Validate the JSON argument shape against the authorized method signature. */
    public function validateParameters(array|bool|float|int|\stdClass|string|null $params): ?string
    {
        return (new RpcParameters())->validate($this->method, $params);
    }

    /**
     * @param array<string|int, array|bool|float|int|\stdClass|string|null> $params
     *
     * @return array<string|int, array|bool|float|int|\stdClass|string|null>
     */
    public function invoke(array $params): array
    {
        return ['result' => $this->method->invokeArgs($this->instance, $params)];
    }
}

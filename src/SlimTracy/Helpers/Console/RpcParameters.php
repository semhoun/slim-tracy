<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Validate and normalize JSON-RPC positional and named arguments. */
final class RpcParameters
{
    /** Validate required arguments before reflection invocation. */
    public function validate(\ReflectionMethod $method, array|bool|float|int|\stdClass|string|null $params): ?string
    {
        if (is_array($params)) {
            return (new RpcPositionalParameters())->validate($method, count($params));
        }

        if ($params instanceof \stdClass) {
            return (new RpcNamedParameters())->validate($method, $params);
        }

        if ($params !== null) {
            return 'Cast of params error';
        }

        return $method->getNumberOfRequiredParameters() > 0 ? 'Empty required params' : null;
    }
}

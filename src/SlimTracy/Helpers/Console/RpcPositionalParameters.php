<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Validate required positional argument counts without rejecting optional defaults. */
final class RpcPositionalParameters
{
    /** Check positional arity before reflection invocation. */
    public function validate(\ReflectionMethod $method, int $count): ?string
    {
        return $count < $method->getNumberOfRequiredParameters() ? sprintf(
            'Check numbers of required params (got %d, expected %d)',
            $count,
            $method->getNumberOfRequiredParameters()
        ) : null;
    }
}

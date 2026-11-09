<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Validate named argument presence and reject names outside the service signature. */
final class RpcNamedParameters
{
    /** Validate required names while leaving optional defaults to PHP itself. */
    public function validate(\ReflectionMethod $method, \stdClass $params): ?string
    {
        foreach ($method->getParameters() as $parameter) {
            if (! $parameter->isOptional() && ! property_exists($params, $parameter->getName())) {
                return $parameter->getName() . ' not found';
            }
        }

        $names = array_map(
            static fn (\ReflectionParameter $param): string => $param->getName(),
            $method->getParameters()
        );
        $unknown = array_diff(array_keys((array) $params), $names);
        return $unknown === [] ? null : 'Unknown named parameter $' . reset($unknown);
    }
}

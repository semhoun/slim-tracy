<?php

declare(strict_types=1);

namespace SlimTracy\Helpers;

use Slim\Interfaces\RouteInterface;

/** Converts a Slim route to the fields shown in the router panel. */
final class RouteDescription
{
    /** @return array<string, string|object|false|null> */
    public static function describe(RouteInterface $route): array
    {
        $callable = $route->getCallable();
        if ($callable instanceof \Closure) {
            $callable = 'Closure';
        } elseif (is_array($callable)) {
            $callable = implode(':', $callable);
        }

        $methods = $route->getMethods();

        return [
            'id' => $route->getIdentifier(),
            'name' => $route->getName() ?? '(unnamed)',
            'pattern' => $route->getPattern(),
            'methods' => array_diff(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $methods) === []
                ? '(any)' : implode(', ', $methods),
            'callable' => $callable,
            'arguments' => json_encode($route->getArguments()),
        ];
    }
}

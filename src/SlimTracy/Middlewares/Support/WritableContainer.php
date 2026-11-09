<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Slim\App;

/** Validates the mutable container required by Tracy's shared services. */
final class WritableContainer
{
    public static function fromApp(App $app): ContainerInterface
    {
        $container = $app->getContainer();
        if (! $container instanceof ContainerInterface || ! is_callable([$container, 'set'])) {
            throw new InvalidArgumentException('Tracy requires a container with a public set() method.');
        }

        return $container;
    }
}

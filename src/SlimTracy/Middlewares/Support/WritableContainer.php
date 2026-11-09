<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Slim\App;

/** Validates the mutable container required by Tracy's shared services. */
final class WritableContainer
{
    /**
     * Retrieves the application container and verifies its mutation API.
     *
     * @param App $app Host application whose container receives Tracy's shared services.
     *
     * @return ContainerInterface Validated container exposing a public set() method.
     *
     * @throws InvalidArgumentException When the container is absent or cannot store services.
     */
    public static function fromApp(App $app): ContainerInterface
    {
        $container = $app->getContainer();
        if (! $container instanceof ContainerInterface || ! is_callable([$container, 'set'])) {
            throw new InvalidArgumentException('Tracy requires a container with a public set() method.');
        }

        return $container;
    }
}

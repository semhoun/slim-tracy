<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use SlimTracy\Collectors\DoctrineCollector;

/** Starts optional collectors before the downstream request handler runs. */
final class CollectorRegistration
{
    /** @param array<string, scalar|array|object|null> $settings */
    public static function register(ContainerInterface $container, array $settings): void
    {
        $service = $settings['configs']['Container']['Doctrine'] ?? '';
        if (
            isset($settings['showDoctrinePanel'])
            && class_exists(Connection::class)
            && $service !== ''
            && $container->has($service)
        ) {
            new DoctrineCollector($container, $service);
        }
    }
}

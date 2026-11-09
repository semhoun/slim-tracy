<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use Doctrine\DBAL\Connection;
use Illuminate\Database\Capsule\Manager;
use Psr\Container\ContainerInterface;
use Twig\Profiler\Profile;

/** Resolves optional integration availability without fetching their services. */
final class OptionalIntegrations
{
    /** @param array<string, scalar|array|object|null> $config */
    public static function available(string $panel, ContainerInterface $container, array $config): bool
    {
        return match ($panel) {
            'showEloquentORMPanel' => class_exists(Manager::class),
            'showTwigPanel' => class_exists(Profile::class) && $container->has($config['Container']['Twig'] ?? ''),
            'showDoctrinePanel' => class_exists(Connection::class) && $container->has('tracy.doctrineQueries'),
            'showConsolePanel' => ! empty($config['ConsoleEnable']),
            default => true,
        };
    }
}

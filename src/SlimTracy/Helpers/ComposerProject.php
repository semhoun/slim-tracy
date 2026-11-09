<?php

declare(strict_types=1);

namespace SlimTracy\Helpers;

/** Joins the requested and installed package sets of a validated Composer project. */
final class ComposerProject
{
    /**
     * @return array{packages: array<string, array<string, \stdClass>>, error: string|null}
     *
     * Composer document readers and package descriptions are stateless utilities.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public static function load(?string $directory, ?string $error): array
    {
        if ($error !== null) {
            return ['packages' => [], 'error' => $error];
        }

        $required = ComposerDocument::read($directory . \DIRECTORY_SEPARATOR . 'composer.json');
        $installed = ComposerDocument::read($directory . \DIRECTORY_SEPARATOR . 'composer.lock');
        $constraints = array_filter($required['data']) + ['require' => [], 'require-dev' => []];
        $versions = array_filter($installed['data']) + ['packages' => [], 'packages-dev' => []];

        return [
            'packages' => [
                'Packages' => ComposerPackages::describe($versions['packages'], $constraints['require']),
                'Dev Packages' => ComposerPackages::describe($versions['packages-dev'], $constraints['require-dev']),
            ],
            'error' => $installed['error'] ?? $required['error'],
        ];
    }
}

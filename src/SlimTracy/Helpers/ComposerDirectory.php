<?php

declare(strict_types=1);

namespace SlimTracy\Helpers;

/** Resolves and validates the host application's Composer directory. */
final class ComposerDirectory
{
    /** @return array{directory: string|null, error: string|null} */
    public static function resolve(?string $directory): array
    {
        $path = (string) ($directory ?? realpath(__DIR__ . '/../../../../../../'));
        $resolved = realpath($path);
        if (! is_dir((string) $resolved)) {
            return ['directory' => null, 'error' => 'Path "' . $path . '" is not a directory.'];
        }

        if (! is_file($resolved . \DIRECTORY_SEPARATOR . 'composer.lock')) {
            return [
                'directory' => null,
                'error' => sprintf("Directory '%s' does not contain the composer.lock file.", $resolved),
            ];
        }

        return ['directory' => $resolved, 'error' => null];
    }
}

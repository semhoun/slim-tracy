<?php

declare(strict_types=1);

namespace SlimTracy\Helpers;

/** Combines lock-file versions with the constraints requested by the application. */
final class ComposerPackages
{
    /**
     * Combines installed package details with requested version constraints.
     *
     * @param array                 $packages Package records from the Composer lock file.
     * @param array<string, string> $required Root project version constraints keyed by package name.
     *
     * @return array<string, object{installed: string, required: string|null, url: string|null}&\stdClass>
     *     Installed versions, requested constraints, and source URLs sorted by package name.
     *
     * @phpstan-param list<array{name: string, version: string,
     *     source?: array{reference?: string, url?: string}}> $packages
     */
    public static function describe(array $packages, array $required): array
    {
        $data = [];
        foreach ($packages as $package) {
            $data[$package['name']] = (object) [
                'installed' => $package['version'] . ($package['version'] === 'dev-master'
                    ? ' #' . substr((string) $package['source']['reference'], 0, 7)
                    : ''),
                'required' => $required[$package['name']] ?? null,
                'url' => isset($package['source']['url'])
                    ? preg_replace('/\.git$/', '', $package['source']['url'])
                    : null,
            ];
        }

        ksort($data);

        return $data;
    }
}

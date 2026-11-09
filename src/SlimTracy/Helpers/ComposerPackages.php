<?php

declare(strict_types=1);

namespace SlimTracy\Helpers;

/** Combines lock-file versions with the constraints requested by the application. */
final class ComposerPackages
{
    /**
     * @param list<array{name: string, version: string, source?: array{reference?: string, url?: string}}> $packages
     * @param array<string, string> $required
     *
     * @return array<string, object{installed: string, required: string|null, url: string|null}&\stdClass>
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

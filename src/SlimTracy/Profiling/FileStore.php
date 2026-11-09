<?php

declare(strict_types=1);

namespace SlimTracy\Profiling;

use RuntimeException;

/** Shared, private, atomically replaced snapshots with bounded retention. */
final readonly class FileStore
{
    /** @var StorageDirectory Private filesystem and locking boundary. */
    private StorageDirectory $directory;

    /** Keep construction side-effect free, even when storage is unavailable. */
    public function __construct(private Configuration $configuration)
    {
        $this->directory = new StorageDirectory($configuration->storageDirectory);
    }

    /** @param array<string, mixed> $profile */
    public function save(array $profile): bool
    {
        if (! $this->configuration->isEnabled() || ! $this->validId($profile['id'] ?? null)) {
            return false;
        }

        return $this->directory->locked(function () use ($profile): bool {
            $this->prune();
            $profile['expiresAt'] = time() + $this->configuration->ttl;
            return $this->write($profile);
        });
    }

    /**
     * Preserve the public id parameter name for PHP named-argument callers.
     *
     * @return array<string, mixed>|null Non-destructively loaded snapshot.
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function get(string $id): ?array
    {
        if (! $this->configuration->isEnabled() || ! $this->validId($id)) {
            return null;
        }

        return $this->directory->locked(fn (): ?array => $this->read($id));
    }

    /** @return array{profiles: list<array<string, mixed>>, nextCursor: ?string} */
    public function listing(?string $after = null, int $limit = 50): array
    {
        if (! $this->configuration->isEnabled()) {
            return ['profiles' => [], 'nextCursor' => null];
        }

        return $this->directory->locked(function () use ($after, $limit): array {
            $this->prune();
            $profiles = [];
            foreach ($this->directory->files() as $file) {
                $profile = $this->read(basename($file, '.json'));
                if ($profile !== null && ($after === null || strcmp($profile['cursor'], $after) > 0)) {
                    $profiles[] = $profile;
                }
            }

            usort($profiles, static fn (array $left, array $right): int => strcmp($left['cursor'], $right['cursor']));
            $profiles = array_slice($profiles, 0, max(1, min(100, $limit)));
            $last = $profiles === [] ? null : $profiles[array_key_last($profiles)]['cursor'];
            return ['profiles' => $profiles, 'nextCursor' => $last ?? $after];
        });
    }

    /**
     * Preserve the public id parameter name for PHP named-argument callers.
     *
     * @param array<string, mixed> $browser Already allowlisted, bounded client declarations.
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function attachBrowser(string $id, array $browser): bool
    {
        if (! $this->configuration->isEnabled() || ! $this->validId($id)) {
            return false;
        }

        return $this->directory->locked(function () use ($id, $browser): bool {
            $profile = $this->read($id);
            if ($profile === null) {
                return false;
            }

            $profile['browser'] = ['source' => 'client'] + $browser;
            return $this->write($profile);
        });
    }

    /** Never use caller input as an arbitrary filename. */
    private function validId(mixed $profileId): bool
    {
        return is_string($profileId) && preg_match('/^[a-f0-9]{32}$/D', $profileId) === 1;
    }

    /** @return array<string, mixed>|null */
    private function read(string $profileId): ?array
    {
        $file = $this->configuration->storageDirectory . '/' . $profileId . '.json';
        if (
            is_link($file) || ! is_file($file) || filemtime($file) < time() - $this->configuration->ttl
            || filesize($file) > $this->configuration->maxProfileBytes
        ) {
            return null;
        }

        $json = @file_get_contents($file);
        $profile = is_string($json) ? json_decode($json, true, 32) : null;
        return $this->validProfile($profile, $profileId) ? $profile : null;
    }

    /** Validate identity, cursor and immutable expiration before returning stored data. */
    private function validProfile(mixed $profile, string $profileId): bool
    {
        return is_array($profile) && ($profile['id'] ?? null) === $profileId
            && is_string($profile['cursor'] ?? null) && is_int($profile['expiresAt'] ?? null)
            && $profile['expiresAt'] > time();
    }

    /**
     * Uses the same stateless text sanitizer as the other exported fields.
     *
     * @param array<string, mixed> $profile Snapshot to atomically persist.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function write(array $profile): bool
    {
        $profile['cursor'] = sprintf('%020d', (int) (microtime(true) * 1000000)) . '-' . bin2hex(random_bytes(8));
        $json = json_encode($profile, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        $maximum = min($this->configuration->maxProfileBytes, $this->configuration->maxTotalBytes);
        if (strlen($json) > $maximum) {
            $profile['collectors'] = [];
            unset($profile['browser']);
            $profile['path'] = StructuredExporter::text($profile['path'] ?? '', 128);
            $profile['truncated'] = true;
            $json = json_encode($profile, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        if (strlen($json) > $maximum) {
            return false;
        }

        $temporary = @tempnam($this->configuration->storageDirectory, '.profile-');
        if ($temporary === false) {
            throw new RuntimeException('Profiler temporary file unavailable.');
        }

        try {
            @chmod($temporary, 0600);
            if (
                @file_put_contents($temporary, $json) !== strlen($json)
                || ! @rename($temporary, $this->configuration->storageDirectory . '/' . $profile['id'] . '.json')
            ) {
                throw new RuntimeException('Profiler write failed.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        $this->prune($this->configuration->storageDirectory . '/' . $profile['id'] . '.json');
        return true;
    }

    /** Remove expired and over-budget snapshots; always retain the just-written snapshot. */
    private function prune(?string $newest = null): void
    {
        $files = $this->directory->files();
        usort($files, static function (string $left, string $right) use ($newest): int {
            $priority = ($right === $newest) <=> ($left === $newest);
            return $priority !== 0 ? $priority : filemtime($right) <=> filemtime($left);
        });
        $bytes = 0;
        $count = 0;
        foreach ($files as $file) {
            $size = filesize($file);
            ++$count;
            if (
                $this->read(basename($file, '.json')) === null
                || $count > $this->configuration->maxProfiles
                || $bytes + $size > $this->configuration->maxTotalBytes
            ) {
                @unlink($file);
                continue;
            }

            $bytes += $size;
        }
    }
}

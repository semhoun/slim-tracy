<?php

declare(strict_types=1);

namespace SlimTracy\Profiling;

use Closure;
use Tracy\Debugger;

/** Immutable limits and the single effective-development guard. */
final readonly class Configuration
{
    /** @param list<string> $origins */
    private function __construct(
        private bool $enabled,
        public array $origins,
        public string $path,
        public string $storageDirectory,
        public int $ttl,
        public int $maxProfiles,
        public int $maxProfileBytes,
        public int $maxTotalBytes,
        public ?Closure $sqlFilter,
        private string $basePath = ''
    ) {
    }

    /** @param array<string, mixed> $settings Tracy settings, not their configs subsection. */
    public static function fromSettings(array $settings, string $basePath = ''): self
    {
        $config = $settings['configs']['AjaxProfiler'] ?? [];
        $config = is_array($config) ? $config : [];

        $path = $config['path'] ?? '/_tracy/api';
        if (! is_string($path) || ! preg_match('~^/[a-zA-Z0-9_/-]+$~D', $path) || trim($path, '/') === '') {
            $path = '/_tracy/api';
        }

        $origins = array_values(array_filter(
            is_array($config['origins'] ?? null) ? $config['origins'] : [],
            static fn (mixed $value): bool => is_string($value)
                && preg_match('~^https?://[^/?#\s]+$~D', $value) === 1
                && ! str_contains($value, '@')
        ));
        $directory = $config['storageDirectory'] ?? null;
        $application = $_SERVER['SCRIPT_FILENAME'] ?? getcwd();
        $directory = is_string($directory) && $directory !== ''
            ? $directory : sys_get_temp_dir() . '/slim-tracy-' . substr(hash('sha256', __DIR__ . $application), 0, 16);
        return new self(
            ($config['enabled'] ?? false) === true,
            $origins,
            rtrim($path, '/'),
            $directory,
            self::limit($config, 'ttl', 600, 1, 86400),
            self::limit($config, 'maxProfiles', 100, 1, 1000),
            self::limit($config, 'maxProfileBytes', 262144, 512, 4194304),
            self::limit($config, 'maxTotalBytes', 10485760, 512, 67108864),
            is_callable($config['sqlFilter'] ?? null) ? Closure::fromCallable($config['sqlFilter']) : null,
            rtrim($basePath, '/')
        );
    }

    /** Require explicit activation and Tracy's resolved development mode. */
    public function isEnabled(): bool
    {
        // Tracy's null means auto-detection has not yet resolved, not development.
        return $this->enabled && Debugger::$productionMode === false;
    }

    /** Bind externally supplied settings to the host router without double-prefixing route patterns. */
    public function withBasePath(string $basePath): self
    {
        return new self(
            $this->enabled,
            $this->origins,
            $this->path,
            $this->storageDirectory,
            $this->ttl,
            $this->maxProfiles,
            $this->maxProfileBytes,
            $this->maxTotalBytes,
            $this->sqlFilter,
            rtrim($basePath, '/')
        );
    }

    /** Returns the externally visible endpoint, including Slim's base path. */
    public function urlPath(): string
    {
        return $this->basePath . $this->path;
    }

    /** Match only the reserved debug route subtree. */
    public function excludes(string $path): bool
    {
        $path = rawurldecode($path);
        $debugPath = rawurldecode($this->urlPath());
        return $path === $debugPath || str_starts_with($path, $debugPath . '/');
    }

    /** @param array<string, mixed> $config */
    private static function limit(array $config, string $key, int $default, int $min, int $max): int
    {
        return is_int($config[$key] ?? null) ? max($min, min($max, $config[$key])) : $default;
    }
}

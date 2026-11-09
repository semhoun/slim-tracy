<?php

declare(strict_types=1);

namespace SlimTracy\Profiling;

use Closure;
use Tracy\Debugger;

/** Immutable limits and the single effective-development guard. */
final readonly class Configuration
{
    /** @var list<string> Validated frontend origins permitted by the API. */
    public array $origins;

    /** Reserved route pattern without the host's base path. */
    public string $path;

    /** Host-specific directory for bounded, shared snapshots. */
    public string $storageDirectory;

    /** Snapshot lifetime in seconds. */
    public int $ttl;

    /** Maximum retained snapshot count. */
    public int $maxProfiles;

    /** Maximum encoded size of one snapshot. */
    public int $maxProfileBytes;

    /** Maximum aggregate snapshot storage size. */
    public int $maxTotalBytes;

    /** Optional host SQL redaction policy. */
    public ?Closure $sqlFilter;

    /**
     * @param array{enabled: bool, origins: list<string>, path: string, storageDirectory: string,
     *     ttl: int, maxProfiles: int, maxProfileBytes: int, maxTotalBytes: int, sqlFilter: ?Closure} $values
     *     Validated settings retained for rebinding without consulting native request state.
     */
    private function __construct(
        private array $values,
        private string $basePath = ''
    ) {
        $this->origins = $values['origins'];
        $this->path = $values['path'];
        $this->storageDirectory = $values['storageDirectory'];
        $this->ttl = $values['ttl'];
        $this->maxProfiles = $values['maxProfiles'];
        $this->maxProfileBytes = $values['maxProfileBytes'];
        $this->maxTotalBytes = $values['maxTotalBytes'];
        $this->sqlFilter = $values['sqlFilter'];
    }

    /** @param array<string, mixed> $settings Tracy settings, not their configs subsection. */
    public static function fromSettings(array $settings, string $basePath = ''): self
    {
        $config = $settings['configs']['AjaxProfiler'] ?? [];
        $config = is_array($config) ? $config : [];

        return new self([
            'enabled' => ($config['enabled'] ?? false) === true,
            'origins' => self::origins($config['origins'] ?? null),
            'path' => self::path($config['path'] ?? null),
            'storageDirectory' => self::storageDirectory($config['storageDirectory'] ?? null),
            'ttl' => self::limit($config, 'ttl', 600, 1, 86400),
            'maxProfiles' => self::limit($config, 'maxProfiles', 100, 1, 1000),
            'maxProfileBytes' => self::limit($config, 'maxProfileBytes', 262144, 512, 4194304),
            'maxTotalBytes' => self::limit($config, 'maxTotalBytes', 10485760, 512, 67108864),
            'sqlFilter' => is_callable($config['sqlFilter'] ?? null) ? $config['sqlFilter'](...) : null,
        ], rtrim($basePath, '/'));
    }

    /** Require explicit activation and Tracy's resolved development mode. */
    public function isEnabled(): bool
    {
        // Tracy's null means auto-detection has not yet resolved, not development.
        return $this->values['enabled'] && Debugger::$productionMode === false;
    }

    /** Bind externally supplied settings to the host router without double-prefixing route patterns. */
    public function withBasePath(string $basePath): self
    {
        return new self($this->values, rtrim($basePath, '/'));
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

    /** Validate the reserved route pattern and remove its trailing slash. */
    private static function path(mixed $path): string
    {
        if (! is_string($path) || ! preg_match('~^/[a-zA-Z0-9_/-]+$~D', $path) || trim($path, '/') === '') {
            return '/_tracy/api';
        }

        return rtrim($path, '/');
    }

    /** @return list<string> Valid HTTP origins without paths, queries or user credentials. */
    private static function origins(mixed $origins): array
    {
        return array_values(array_filter(
            is_array($origins) ? $origins : [],
            static fn (mixed $value): bool => is_string($value)
                && preg_match('~^https?://[^/?#\s]+$~D', $value) === 1
                && ! str_contains($value, '@')
        ));
    }

    /**
     * Resolve explicit storage or isolate applications sharing the same library installation.
     *
     * Native entrypoint identity is required here; rebinding never rereads this boundary.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private static function storageDirectory(mixed $directory): string
    {
        if (is_string($directory) && $directory !== '') {
            return $directory;
        }

        $application = $_SERVER['SCRIPT_FILENAME'] ?? getcwd();
        return sys_get_temp_dir() . '/slim-tracy-' . substr(hash('sha256', __DIR__ . $application), 0, 16);
    }

    /** @param array<string, mixed> $config Settings whose integer limit will be clamped. */
    private static function limit(array $config, string $key, int $default, int $min, int $max): int
    {
        return is_int($config[$key] ?? null) ? max($min, min($max, $config[$key])) : $default;
    }
}

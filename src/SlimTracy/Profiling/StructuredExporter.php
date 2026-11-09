<?php

declare(strict_types=1);

namespace SlimTracy\Profiling;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use Psr\Container\ContainerInterface;
use SlimTracy\Collectors\DoctrineLogger\Queries;
use SlimTracy\Helpers\Profiler\Profiler;
use SlimTracy\Helpers\Profiler\ProfilerService;
use Twig\Profiler\Profile;

/** Allowlist-only collector snapshots: bindings and arbitrary metadata never leave the process. */
final class StructuredExporter
{
    /** @var Configuration Effective development settings. */
    private readonly Configuration $configuration;

    /** @var \WeakMap<object, int> Last observed offsets, without retaining optional services. */
    private \WeakMap $observed;

    /** @var \WeakMap<object, int> Baselines for the current handler invocation. */
    private \WeakMap $offsets;

    /** @var \WeakMap<Profile, bool> Previously exported Twig nodes, without retaining template profiles. */
    private \WeakMap $twigObserved;

    /** @var \WeakMap<Profile, bool> Twig nodes belonging to earlier snapshots. */
    private \WeakMap $twigOffsets;

    /** @var int Bounded traversal work, including previously exported tree nodes. */
    private int $twigVisited = 0;

    /** @param array<string, mixed> $settings */
    public function __construct(
        private readonly ?ContainerInterface $container,
        private readonly array $settings,
        ?Configuration $configuration = null
    ) {
        $this->configuration = $configuration ?? Configuration::fromSettings($settings);
        $this->observed = new \WeakMap();
        $this->offsets = new \WeakMap();
        $this->twigObserved = new \WeakMap();
        $this->twigOffsets = new \WeakMap();
    }

    /** Begin a snapshot without resolving container services or creating optional connections. */
    public function start(): void
    {
        if (! $this->configuration->isEnabled()) {
            return;
        }

        $this->offsets = new \WeakMap();
        $this->twigOffsets = clone $this->twigObserved;
        $this->twigVisited = 0;
        foreach ($this->observed as $source => $count) {
            $this->offsets[$source] = match (true) {
                $source instanceof Queries => count($source->getQueries()),
                $source instanceof Connection => count($source->getQueryLog()),
                default => $count,
            };
        }

        if (ProfilerService::hasInstance()) {
            $service = ProfilerService::getInstance();
            $this->offsets[$service] = count($service->getProfiles());
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function export(): array
    {
        if (! $this->configuration->isEnabled()) {
            return [];
        }

        $collectors = [];
        if ($this->container?->has('tracy.doctrineQueries')) {
            $queries = $this->container->get('tracy.doctrineQueries');
            if ($queries instanceof Queries) {
                $collectors['doctrine'] = $this->sql(
                    $this->sinceStart($queries, $queries->getQueries()),
                    'sql',
                    'executionMS',
                    1000
                );
            }
        }

        $eloquent = $this->eloquent();
        if ($eloquent !== null) {
            $collectors['eloquent'] = $eloquent;
        }

        if (Profiler::isEnabled() || ProfilerService::hasInstance()) {
            $service = ProfilerService::getInstance();
            $profiles = $this->sinceStart($service, $service->getProfiles());
            $items = [];
            foreach (array_slice($profiles, 0, 1000) as $profile) {
                $meta = $profile->metadata();
                $items[] = [
                    'label' => self::text($meta['start_label'] ?? ''),
                    'durationMs' => max(0, $profile->duration() * 1000),
                    'memoryBytes' => $profile->memoryUsageChange(),
                ];
            }

            $collectors['manual'] = ['available' => true, 'profiles' => $items, 'truncated' => count($profiles) > 1000];
        }

        $twigService = $this->settings['configs']['Container']['Twig'] ?? null;
        if (class_exists(Profile::class) && is_string($twigService) && $this->container?->has($twigService)) {
            $profile = $this->container->get($twigService);
            if ($profile instanceof Profile) {
                $items = [];
                $this->twig($profile, $items);
                $collectors['twig'] = [
                    'available' => true,
                    'profiles' => $items,
                    'truncated' => count($items) >= 1000 || $this->twigVisited >= 4096,
                ];
            }
        }

        return $collectors;
    }

    /** Normalize strings without invoking arbitrary object conversion hooks. */
    public static function text(mixed $value, int $limit = 512): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        return mb_strcut(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '', 0, $limit, 'UTF-8');
    }

    /** @return array<string, mixed>|null */
    private function eloquent(): ?array
    {
        if (! class_exists(Manager::class)) {
            return null;
        }

        try {
            // Capsule has no public global-instance availability accessor.
            $connection = Manager::connection();
        } catch (\Throwable) {
            return null;
        }

        return $connection->logging()
            ? $this->sql($this->sinceStart($connection, $connection->getQueryLog()), 'query', 'time', 1) : null;
    }

    /**
     * @template T
     *
     * @param list<T> $items Entries from a known collector, never arbitrary serialized objects.
     *
     * @return list<T>
     */
    private function sinceStart(object $source, array $items): array
    {
        $offset = $this->offsets[$source] ?? 0;
        $count = count($items);
        $this->observed[$source] = $count;
        return array_slice($items, $offset <= $count ? $offset : 0);
    }

    /**
     * @param list<array<string, mixed>> $queries Logged statements without evaluating their bindings.
     *
     * @return array<string, mixed>
     */
    private function sql(array $queries, string $sqlKey, string $timeKey, int $multiplier): array
    {
        $items = [];
        $truncated = count($queries) > 1000;
        foreach (array_slice($queries, 0, 1000) as $query) {
            $sql = $query[$sqlKey] ?? '';
            if (! is_string($sql)) {
                continue;
            }

            if ($this->configuration->sqlFilter instanceof \Closure) {
                $sql = ($this->configuration->sqlFilter)($sql);
            }

            if (! is_string($sql)) {
                continue;
            }

            $duration = $query[$timeKey] ?? 0;
            $normalized = self::text($sql, 8192);
            $shortened = strlen($normalized) < strlen($sql);
            $truncated = $truncated || $shortened;
            $items[] = [
                'sql' => $normalized,
                'durationMs' => is_numeric($duration) ? max(0, (float) $duration * $multiplier) : 0,
                'truncated' => $shortened,
            ];
        }

        return ['available' => true, 'queries' => $items, 'truncated' => $truncated];
    }

    /** @param list<array<string, mixed>> $items */
    private function twig(Profile $profile, array &$items, int $depth = 0): void
    {
        if (count($items) >= 1000 || $depth >= 20 || $this->twigVisited >= 4096) {
            return;
        }

        ++$this->twigVisited;
        $this->twigObserved[$profile] = true;
        if (! isset($this->twigOffsets[$profile])) {
            $items[] = [
                'name' => self::text($profile->getName()),
                'template' => self::text($profile->getTemplate()),
                'type' => self::text($profile->getType()),
                'durationMs' => max(0, $profile->getDuration() * 1000),
                'memoryBytes' => $profile->getMemoryUsage(),
                'depth' => $depth,
            ];
        }

        foreach ($profile->getProfiles() as $child) {
            $this->twig($child, $items, $depth + 1);
            if (count($items) >= 1000 || $this->twigVisited >= 4096) {
                break;
            }
        }
    }
}

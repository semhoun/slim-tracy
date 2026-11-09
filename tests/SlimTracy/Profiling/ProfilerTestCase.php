<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Profiling;

use PHPUnit\Framework\TestCase;
use DirectoryIterator;
use SlimTracy\Profiling\Configuration;
use Tracy\Debugger;

/** Shared isolated storage and development-mode fixtures for profiling tests. */
abstract class ProfilerTestCase extends TestCase
{
    /** @var string Unique private storage path for the current test. */
    protected string $directory;

    /** @var bool|null Tracy mode restored after the current test. */
    private ?bool $productionMode;

    /** Save Tracy's mode and allocate a unique storage path. */
    protected function setUp(): void
    {
        $this->productionMode = Debugger::$productionMode;
        Debugger::$productionMode = false;
        $this->directory = sys_get_temp_dir() . '/slim-tracy-test-' . bin2hex(random_bytes(8));
    }

    /** Restore Tracy's mode and remove generated profile files. */
    protected function tearDown(): void
    {
        Debugger::$productionMode = $this->productionMode;
        if (is_dir($this->directory)) {
            foreach (new DirectoryIterator($this->directory) as $file) {
                if (! $file->isDot()) {
                    unlink($file->getPathname());
                }
            }
            rmdir($this->directory);
        }
    }

    /**
     * Build isolated settings through the public static configuration factory.
     *
     * @param array $overrides Configuration values to replace for this scenario.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function configuration(array $overrides = []): Configuration
    {
        return Configuration::fromSettings(['configs' => ['AjaxProfiler' => array_replace([
            'enabled' => true, 'storageDirectory' => $this->directory,
        ], $overrides)]]);
    }

    /** Return a minimal profile with a fresh correlation identifier. */
    protected function profile(): array
    {
        return [
            'schemaVersion' => 1, 'id' => bin2hex(random_bytes(16)), 'method' => 'GET', 'path' => '/test',
            'status' => 200, 'startedAt' => '2026-11-09T12:00:00Z', 'durationMs' => 1,
            'memoryBytes' => 1024, 'collectors' => [],
        ];
    }
}

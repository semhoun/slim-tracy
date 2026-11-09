<?php

declare(strict_types=1);

namespace SlimTracy\Profiling;

use DirectoryIterator;
use RuntimeException;

/** Owns private-directory validation and short-lived filesystem locks. */
final readonly class StorageDirectory
{
    /**
     * Keep filesystem access deferred until an explicitly guarded operation.
     *
     * @param string $path Application-specific snapshot directory.
     */
    public function __construct(private string $path)
    {
    }

    /**
     * Run a snapshot operation while holding the aggregate-budget lock.
     *
     * @param callable $operation Bounded snapshot read or write operation.
     *
     * @return mixed Result produced by the operation.
     */
    public function locked(callable $operation): mixed
    {
        $this->prepare();
        $lock = $this->openLock();
        try {
            if (! @flock($lock, LOCK_EX)) {
                throw new RuntimeException('Profiler lock unavailable.');
            }

            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Enumerate valid snapshots and discard abandoned atomic-write files.
     *
     * @return list<string> Snapshot paths, called only while holding the lock.
     */
    public function files(): array
    {
        $files = [];
        $scanned = 0;
        foreach (new DirectoryIterator($this->path) as $file) {
            ++$scanned;
            if ($scanned > 4096) {
                throw new RuntimeException('Profiler directory entry limit exceeded.');
            }

            if ($file->isLink() || ! $file->isFile()) {
                continue;
            }

            if (str_starts_with($file->getFilename(), '.profile-')) {
                @unlink($file->getPathname());
                continue;
            }

            if (preg_match('/^[a-f0-9]{32}\.json$/D', $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** Create a private directory and validate its resolved location. */
    private function prepare(): void
    {
        if (
            is_link($this->path) || (! is_dir($this->path)
            && ! @mkdir($this->path, 0700, true) && ! is_dir($this->path))
        ) {
            throw new RuntimeException('Profiler storage unavailable.');
        }

        $this->assertOutsideDocumentRoot();
        if (! @chmod($this->path, 0700)) {
            throw new RuntimeException('Profiler storage permissions unavailable.');
        }
    }

    /**
     * Check the native server document root before storing any profile data.
     *
     * The filesystem boundary must observe the same native environment as Tracy.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function assertOutsideDocumentRoot(): void
    {
        $real = realpath($this->path);
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $root = $documentRoot !== '' ? realpath($documentRoot) : false;
        if ($real === false) {
            throw new RuntimeException('Profiler storage unavailable.');
        }

        if (
            $root !== false && $root !== '' && ($real === $root
            || str_starts_with($real, $root . DIRECTORY_SEPARATOR))
        ) {
            throw new RuntimeException('Profiler storage must be outside the document root.');
        }
    }

    /**
     * Open the private lock file without following a symlink.
     *
     * @return resource Opened lock file, released by locked().
     */
    private function openLock()
    {
        $path = $this->path . '/.lock';
        if (is_link($path)) {
            throw new RuntimeException('Profiler lock unavailable.');
        }

        $lock = @fopen($path, 'c');
        if ($lock === false) {
            throw new RuntimeException('Profiler lock unavailable.');
        }

        @chmod($path, 0600);
        return $lock;
    }
}

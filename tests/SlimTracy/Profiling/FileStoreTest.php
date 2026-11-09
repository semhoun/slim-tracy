<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Profiling;

use SlimTracy\Profiling\Configuration;
use DirectoryIterator;
use SlimTracy\Profiling\FileStore;
use Tracy\Debugger;

/** Verifies configuration guards and private bounded snapshot storage. */
final class FileStoreTest extends ProfilerTestCase
{
    /**
     * Exercise the public static configuration factory's strict development guard.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function testGuardRequiresExplicitEnabledAndResolvedDevelopment(): void
    {
        foreach ([null, false, 1, 'true', true] as $enabled) {
            foreach ([null, false, true] as $mode) {
                Debugger::$productionMode = $mode;
                self::assertSame(
                    $enabled === true && $mode === false,
                    $this->configuration(['enabled' => $enabled])->isEnabled()
                );
            }
        }
        self::assertFalse(Configuration::fromSettings([])->isEnabled());
        self::assertSame('/_tracy/api', $this->configuration(['path' => '/'])->path);
        self::assertSame('/_tracy/api', $this->configuration(['path' => '//'])->path);
        self::assertTrue($this->configuration()->excludes('/%5ftracy/api/profiles'));
        self::assertFalse($this->configuration()->excludes('/_tracy/apiculture'));
    }

    /** Verify permissions, truncation, eviction, expiry, and production guards. */
    public function testStoreNonDestructivePrivateTruncatedBoundedAndExpired(): void
    {
        $configuration = $this->configuration(['maxProfiles' => 2, 'maxProfileBytes' => 600, 'maxTotalBytes' => 1200]);
        $store = new FileStore($configuration);
        $first = $this->profile();
        $first['collectors'] = ['doctrine' => ['sql' => str_repeat('secret', 1000)]];
        self::assertTrue($store->save($first));
        self::assertTrue($store->get($first['id'])['truncated']);
        self::assertSame([], $store->get($first['id'])['collectors']);
        self::assertNotNull($store->get($first['id']));
        self::assertSame(0700, fileperms($this->directory) & 0777);
        self::assertSame(0600, fileperms($this->directory . '/' . $first['id'] . '.json') & 0777);
        $store->save($this->profile());
        $store->save($this->profile());
        self::assertCount(2, $store->listing()['profiles']);
        self::assertNull($store->get('../outside'));
        $profile = $store->listing()['profiles'][0];
        touch($this->directory . '/' . $profile['id'] . '.json', time() - 601);
        clearstatcache();
        self::assertNull($store->get($profile['id']));
        self::assertCount(1, $store->listing()['profiles']);
        Debugger::$productionMode = true;
        self::assertNull($store->get($first['id']));
        self::assertFalse($store->save($first));
        self::assertSame([], $store->listing()['profiles']);
    }

    /** Verify browser attachments advance the incremental listing cursor. */
    public function testIncrementalPaginationAndBrowserUpdates(): void
    {
        $store = new FileStore($this->configuration());
        $profile = $this->profile();
        $store->save($profile);
        $page = $store->listing(null, 1);
        self::assertCount(1, $page['profiles']);
        self::assertSame([], $store->listing($page['nextCursor'])['profiles']);
        self::assertTrue($store->attachBrowser($profile['id'], ['durationMs' => 15]));
        $update = $store->listing($page['nextCursor']);
        self::assertSame('client', $update['profiles'][0]['browser']['source']);
        self::assertSame(15, $update['profiles'][0]['browser']['durationMs']);
        self::assertFalse($store->attachBrowser(str_repeat('0', 32), []));
    }

    /** Preserve public named arguments while private identifier variables remain descriptive. */
    public function testPreservesNamedProfileIdentifierArguments(): void
    {
        $store = new FileStore($this->configuration());
        $profile = $this->profile();
        self::assertTrue($store->save($profile));
        self::assertTrue($store->attachBrowser(id: $profile['id'], browser: ['durationMs' => 5]));
        self::assertSame($profile['id'], $store->get(id: $profile['id'])['id']);
    }

    /** Verify a browser update cannot refresh an expired profile's lifetime. */
    public function testBrowserAttachmentCannotExtendOriginalExpiry(): void
    {
        $store = new FileStore($this->configuration());
        $profile = $this->profile();
        $store->save($profile);
        $stored = $store->get($profile['id']);
        self::assertTrue($store->attachBrowser($profile['id'], ['durationMs' => 2]));
        self::assertSame($stored['expiresAt'], $store->get($profile['id'])['expiresAt']);
        $stored['expiresAt'] = time() - 1;
        $file = $this->directory . '/' . $profile['id'] . '.json';
        file_put_contents($file, json_encode($stored));
        touch($file);
        clearstatcache();
        self::assertNull($store->get($profile['id']));
        self::assertFalse($store->attachBrowser($profile['id'], ['durationMs' => 2]));
        self::assertSame([], $store->listing()['profiles']);
        self::assertFileDoesNotExist($file);
    }

    /** Verify aggregate disk usage stays bounded even with oversized paths. */
    public function testAggregateBytesAndLongPathsAreBounded(): void
    {
        $store = new FileStore($this->configuration(['maxProfileBytes' => 512, 'maxTotalBytes' => 700]));
        for ($index = 0; $index < 5; ++$index) {
            $profile = $this->profile();
            $profile['path'] = '/' . str_repeat('a', 1024);
            self::assertTrue($store->save($profile));
            self::assertNotNull($store->get($profile['id']));
        }
        $bytes = 0;
        foreach (new DirectoryIterator($this->directory) as $file) {
            if ($file->getExtension() === 'json') {
                $bytes += $file->getSize();
            }
        }
        self::assertLessThanOrEqual(700, $bytes);
    }
}

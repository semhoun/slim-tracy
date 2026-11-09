<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use SlimTracy\Profiling\Configuration;
use Tracy\Debugger;
use Tracy\SessionStorage;

/** Avoids Tracy's request-lifetime session lock on API traffic. */
final class NativeSessionIsolation implements SessionStorage
{
    /** @var bool Whether this request has already installed the stateless adapter. */
    private static bool $installed = false;

    /** @var array<string, mixed> Non-persistent native scratch data, never API profiles. */
    private array $data = [];

    /**
     * Preserves native deferred HTML assets, but never opens their session for API calls.
     *
     * Tracy's static storage setter must run before enable(). Native globals select isolation before PSR-7 exists.
     *
     * @param Configuration $configuration Effective profiler settings and reserved route prefix.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function configure(Configuration $configuration): void
    {
        if (! $configuration->isEnabled() || self::$installed) {
            return;
        }

        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $debug = $configuration->excludes($path);
        $destination = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? null;
        $document = in_array($destination, ['document', 'iframe', 'frame'], true)
            || ($destination === null && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html'));
        if (! $debug && ($document || isset($_GET['_tracy_bar']))) {
            return;
        }

        try {
            Debugger::setSessionStorage(new self());
            self::$installed = true;
        } catch (\Exception) {
            // A host-owned storage must not be replaced. Bootstrap order remains the host's responsibility.
            error_log('SlimTracy API profiler: native storage was initialized before API isolation.');
        }
    }

    /** Stateless API requests cannot use native deferred content. */
    public function isAvailable(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function &getData(): array
    {
        return $this->data;
    }
}

<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use SlimTracy\Profiling\Configuration;
use Tracy\Debugger;

/** Applies global Tracy configuration before request collection starts. */
final class DebuggerBootstrap
{
    /** @param array<string, scalar|array|object|null> $config */
    public static function configure(array $config, string $basePath = ''): void
    {
        if (isset($config['Debugger'])) {
            $mode = $config['Debugger']['mode'] ?? null;
            if ($mode !== null || Debugger::$productionMode === null) {
                Debugger::$productionMode = is_bool($mode) ? $mode : ! Debugger::detectDebugMode($mode);
            }
        }

        $profiler = Configuration::fromSettings(['configs' => $config], $basePath);
        AjaxAssets::register($profiler);
        self::guardDebugDispatch($profiler);
        NativeSessionIsolation::configure($profiler);
        if (isset($config['Debugger'])) {
            $debugger = $config['Debugger'];
            Debugger::enable(
                $debugger['mode'] ?? null,
                $debugger['logDirectory'] ?? null,
                $debugger['email'] ?? null,
            );
        }

        Debugger::$showBar = (int) ($config['ShowBar'] ?? Debugger::$showBar) > 0;
    }

    /** Prevents native assets from bypassing guarded profiler routes. */
    private static function guardDebugDispatch(Configuration $profiler): void
    {
        $requestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (! $profiler->excludes($requestPath)) {
            return;
        }

        if (! $profiler->isEnabled() || ($_GET['_tracy_bar'] ?? '') !== 'js') {
            // Do not let deferred host panels bypass the guarded PSR-7 routes.
            unset($_GET['_tracy_bar']);
        }

        unset($_SERVER['HTTP_X_TRACY_AJAX']);
    }
}

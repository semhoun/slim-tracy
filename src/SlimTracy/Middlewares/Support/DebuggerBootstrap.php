<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use Tracy\Debugger;

/** Applies global Tracy configuration before request collection starts. */
final class DebuggerBootstrap
{
    /** @param array<string, scalar|array|object|null> $config */
    public static function configure(array $config): void
    {
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
}

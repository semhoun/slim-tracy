<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use RuntimeException;
use SlimTracy\Profiling\Configuration;
use Tracy\Debugger;

/** Localizes the tested internal Tracy browser integration. */
final class AjaxAssets
{
    /** Registers the replacement before enable() can dispatch native assets. */
    public static function register(?Configuration $configuration = null): void
    {
        if (Debugger::Version !== '2.12.2') {
            throw new RuntimeException('SlimTracy AJAX compatibility requires Tracy 2.12.2.');
        }

        $file = dirname(__DIR__, 4) . '/web/disable-native-ajax.js';
        if (! in_array($file, Debugger::$customJsFiles, true)) {
            array_unshift(Debugger::$customJsFiles, $file);
        }

        if (! $configuration?->isEnabled()) {
            return;
        }

        foreach (['ajax-profiler.js', 'api-panel.js'] as $name) {
            $asset = dirname(__DIR__, 4) . '/web/' . $name;
            if (! in_array($asset, Debugger::$customJsFiles, true)) {
                Debugger::$customJsFiles[] = $asset;
            }
        }
    }
}

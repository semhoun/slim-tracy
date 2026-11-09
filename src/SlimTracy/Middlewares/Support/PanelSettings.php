<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use Psr\Http\Message\ServerRequestInterface;

/** Applies the browser's panel selection without replacing server configuration. */
final class PanelSettings
{
    /**
     * Resolves the browser's panel choices against the server defaults.
     *
     * @param ServerRequestInterface                $request  Request containing the optional panel-selection cookie.
     * @param array<string, scalar|array|object|null> $defaults Server-defined panel selections and configuration.
     *
     * @return array<string, scalar|array|object|null> Defaults or the cookie-selected panel flags.
     */
    public static function resolve(ServerRequestInterface $request, array $defaults): array
    {
        $cookie = $request->getCookieParams()['tracyPanelsEnabled'] ?? '[]';
        $selected = json_decode(is_string($cookie) ? $cookie : '[]', true);
        if (! is_array($selected) || $selected === []) {
            return $defaults;
        }

        $names = array_filter($selected, is_string(...));
        return array_replace(array_fill_keys(array_keys($defaults), null), array_fill_keys($names, 1));
    }
}

<?php

declare(strict_types=1);

namespace SlimTracy\Controllers\Support;

use Psr\Http\Message\ServerRequestInterface;
use SlimTracy\Helpers\Console\WebConsoleRPCServer;

/** Supplies explicit request data and configuration to the console service. */
final class ConsoleTransport
{
    /**
     * @param array<string, scalar|array|object|null> $config Host console authentication and directory settings.
     *
     * @return array<int|string, array|bool|float|int|\stdClass|string|null>
     */
    public static function execute(ServerRequestInterface $request, array $config): array
    {
        $console = new WebConsoleRPCServer(
            $config['ConsoleAccounts'] ?? [],
            (bool) ($config['ConsoleNoLogin'] ?? false),
            (string) ($config['ConsoleHashAlgorithm'] ?? ''),
            $config['ConsoleHomeDirectory'] ?? '',
        );
        $query = $request->getQueryParams();
        return $console->execute(
            self::input($request),
            $request->getMethod(),
            array_key_exists('smd', $query),
            (string) $request->getUri()->withQuery('')->withFragment(''),
        );
    }

    /** Read the legacy rawRequest override or the unmodified request body. */
    private static function input(ServerRequestInterface $request): string
    {
        $input = $request->getQueryParams()['rawRequest'] ?? (string) $request->getBody();
        return is_string($input) ? $input : '';
    }
}

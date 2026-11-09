<?php

declare(strict_types=1);

namespace SlimTracy\Profiling;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Exact-origin CORS additions; host policy is never broadened or replaced. */
final class Cors
{
    /** Accept direct requests, same-origin browsers, or exact configured origins. */
    public static function allowed(ServerRequestInterface $request, Configuration $configuration): bool
    {
        $origin = $request->getHeaderLine('Origin');
        if ($origin === '') {
            return true;
        }

        $uri = $request->getUri();
        $own = $uri->getScheme() . '://' . $uri->getHost();
        $port = $uri->getPort();
        if ($port !== null) {
            $own .= ':' . $port;
        }

        return $origin === $own || in_array($origin, $configuration->origins, true);
    }

    /** Add profiler headers without replacing existing host CORS policy. */
    public static function apply(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Configuration $configuration,
        bool $preflight = false
    ): ResponseInterface {
        $vary = array_filter(array_map(trim(...), explode(',', $response->getHeaderLine('Vary'))));
        if (! in_array('origin', array_map(strtolower(...), $vary), true)) {
            $response = $response->withAddedHeader('Vary', 'Origin');
        }

        $origin = $request->getHeaderLine('Origin');
        if ($origin === '' || ! self::allowed($request, $configuration)) {
            return $response;
        }

        if (! $response->hasHeader('Access-Control-Allow-Origin')) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        }

        $exposed = $response->getHeaderLine('Access-Control-Expose-Headers');
        $headers = array_map(strtolower(...), array_map(trim(...), explode(',', $exposed)));
        if (! in_array(strtolower(RequestProfiler::HEADER), $headers, true)) {
            $response = $response->withAddedHeader('Access-Control-Expose-Headers', RequestProfiler::HEADER);
        }

        if ($preflight) {
            if (! $response->hasHeader('Access-Control-Allow-Methods')) {
                $response = $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            }

            if (! $response->hasHeader('Access-Control-Allow-Headers')) {
                $response = $response->withHeader('Access-Control-Allow-Headers', 'Content-Type');
            }
        }

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace SlimTracy\Controllers\Support;

/** Converts command output without altering JSON-RPC errors or other result fields. */
final class ConsoleOutput
{
    /**
     * @param array<int|string, array|bool|float|int|\stdClass|string|null> $response JSON-RPC result to normalize.
     *
     * @return array<int|string, array|bool|float|int|\stdClass|string|null>
     */
    public static function convert(array $response, string $encoding): array
    {
        if ($encoding !== '' && $encoding !== 'UTF-8' && isset($response['result']['output'])) {
            $response['result']['output'] = mb_convert_encoding($response['result']['output'], 'UTF-8', $encoding);
        }

        return $response;
    }
}

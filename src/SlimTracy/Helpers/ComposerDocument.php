<?php

declare(strict_types=1);

namespace SlimTracy\Helpers;

/**
 * Decodes a Composer JSON document and reports syntax errors.
 *
 * @phpstan-type JsonValue array<array-key, JsonValue>|bool|float|int|string|null
 */
final class ComposerDocument
{
    /** @return array{data: array<string, JsonValue>, error: string|null} */
    public static function read(string $file): array
    {
        if (! is_file($file)) {
            return ['data' => [], 'error' => null];
        }

        $decoded = json_decode(file_get_contents($file), true);
        if (! is_array($decoded)) {
            return ['data' => [], 'error' => json_last_error_msg()];
        }

        return ['data' => $decoded, 'error' => null];
    }
}

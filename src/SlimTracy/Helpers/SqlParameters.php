<?php

declare(strict_types=1);

namespace SlimTracy\Helpers;

use Doctrine\DBAL\ParameterType;

/** Formats the positional parameter lists displayed by the SQL panel. */
final class SqlParameters
{
    /** @param array<scalar|resource|null>|null $data */
    public static function values(?array $data): string
    {
        $lines = [];
        foreach (array_values($data ?? []) as $index => $entry) {
            $lines[] = ($index + 1) . ': ' . $entry;
        }

        return implode("\n", $lines);
    }

    /** @param array<ParameterType>|null $data */
    public static function types(?array $data): string
    {
        $lines = [];
        foreach (array_values($data ?? []) as $index => $entry) {
            $lines[] = ($index + 1) . ': ' . $entry->name;
        }

        return implode("\n", $lines);
    }
}

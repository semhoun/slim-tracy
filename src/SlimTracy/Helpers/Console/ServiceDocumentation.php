<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Parse the human-readable summary shared by class and method SMD descriptions. */
final class ServiceDocumentation
{
    /** Extract a human-readable description, tolerating undocumented services. */
    public function description(string $comment): string
    {
        if (preg_match('/\*\s+([^@]*)\s+/s', $comment, $matches)) {
            return str_replace('*', "\n", trim(trim($matches[1], '*')));
        }

        return '';
    }
}

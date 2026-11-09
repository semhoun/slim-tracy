<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Resolve the directory implied by an explicit or incomplete completion path. */
final class ConsoleCompletionPattern
{
    /** Normalize incomplete paths while retaining explicitly requested directories. */
    public function directory(string $pattern): string
    {
        if ($pattern === '' || is_dir($pattern)) {
            return $pattern;
        }

        $directory = dirname($pattern);
        return $directory === '.' ? '' : $directory;
    }
}

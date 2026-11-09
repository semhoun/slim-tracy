<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** List naturally sorted directory entries with the console's path prefix convention. */
final class ConsoleCompletion
{
    /** @return array{completion: list<string>} */
    public function complete(string $pattern): array
    {
        $pattern = (new ConsoleCompletionPattern())->directory($pattern);
        $path = $pattern === '' ? '.' : $pattern;
        if (! is_dir($path)) {
            return ['completion' => []];
        }

        $entries = array_diff(scandir($path), ['.', '..']);
        natsort($entries);
        $prefix = $pattern === '' ? '' : rtrim($pattern, '/') . '/';
        $completion = array_map(static fn (string $entry): string => $prefix . $entry, $entries);
        return ['completion' => array_values($completion)];
    }
}

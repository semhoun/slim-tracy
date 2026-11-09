<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Apply the console cd command and return its command-specific diagnostics. */
final class ConsoleDirectoryChange
{
    /** @return array<string, array|string> */
    public function change(string $path, string $home): array
    {
        $path = trim($path);
        $path = $path === '' ? $home : $path;
        if (! is_dir($path)) {
            return ['output' => 'cd: ' . $path . ': No such directory'];
        }

        return chdir($path) ? ['environment' => (new ConsoleCommandEnvironment())->current()] : [
            'output' => 'cd: ' . $path . ': Unable to change directory',
        ];
    }
}

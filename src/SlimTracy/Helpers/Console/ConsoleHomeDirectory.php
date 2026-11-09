<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Resolve immutable default or per-user console home directory configuration. */
final readonly class ConsoleHomeDirectory
{
    /** @param string|array<string, string> $directory */
    public function __construct(private string|array $directory)
    {
    }

    /** Resolve a user's home without changing the current process directory. */
    public function resolve(string $user): string
    {
        $home = is_array($this->directory) ? ($this->directory[$user] ?? '') : $this->directory;
        return $home === '' ? (string) getcwd() : $home;
    }
}

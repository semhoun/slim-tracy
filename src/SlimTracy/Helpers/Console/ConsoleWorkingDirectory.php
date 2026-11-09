<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Restore a console session's working directory without mutating shared configuration. */
final readonly class ConsoleWorkingDirectory
{
    /** @param string|array<string, string> $homeDirectory */
    public function __construct(private string|array $homeDirectory = '')
    {
    }

    /** Resolve a user's configured home, falling back to the current directory. */
    public function home(string $user): string
    {
        return (new ConsoleHomeDirectory($this->homeDirectory))->resolve($user);
    }

    /** @return array{path: string|false, hostname: string|false} */
    public function environment(): array
    {
        return (new ConsoleCommandEnvironment())->current();
    }

    /**
     * @param array<string, array|bool|float|int|\stdClass|string|null>|\stdClass|null $environment
     *
     * @return array<string, array|string>|null
     */
    public function restore(array|\stdClass|null $environment, string $user): ?array
    {
        return (new ConsoleCommandEnvironment())->restore($environment, $this->home($user));
    }

    /** @return array<string, array|string> */
    public function change(string $path, string $user): array
    {
        return (new ConsoleDirectoryChange())->change($path, $this->home($user));
    }
}

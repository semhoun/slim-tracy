<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Restore and report the process environment used by an authenticated command. */
final class ConsoleCommandEnvironment
{
    /** @return array{path: string|false, hostname: string|false} */
    public function current(): array
    {
        return ['path' => getcwd(), 'hostname' => gethostname()];
    }

    /**
     * @param array<string, array|bool|float|int|\stdClass|string|null>|\stdClass|null $environment
     *
     * @return array<string, array|string>|null
     */
    public function restore(array|\stdClass|null $environment, string $home): ?array
    {
        $values = (array) $environment;
        $path = $values['path'] ?? '';
        $path = is_string($path) && $path !== '' ? $path : $home;
        if (! is_dir($path)) {
            return $this->failure('Current working directory not found, updating current directory');
        }

        return chdir($path) ? null : $this->failure(
            'Unable to change directory to current working directory, updating current directory'
        );
    }

    /** @return array{output: string, environment: array<string, string|false>} */
    private function failure(string $message): array
    {
        return ['output' => $message, 'environment' => $this->current()];
    }
}

<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

use SlimTracy\Exceptions\IncorrectUserOrPassword;

/** Verify user credentials before issuing a console session token. */
final readonly class ConsoleCredentials
{
    /** @param array<string, string> $accounts */
    public function __construct(private array $accounts, private string $passwordHashAlgorithm)
    {
    }

    /** Authenticate credentials and issue the legacy token format. */
    public function login(string $user, string $password): string
    {
        $user = trim($user);
        $password = trim($password);
        $stored = $this->accounts[$user] ?? '';
        if (in_array('', [$user, $password, $stored], true)) {
            throw new IncorrectUserOrPassword();
        }

        if ($this->passwordHashAlgorithm !== '') {
            $password = hash($this->passwordHashAlgorithm, $password);
        }

        if (! hash_equals($stored, $password)) {
            throw new IncorrectUserOrPassword();
        }

        return $user . ':' . hash('sha256', $stored);
    }
}

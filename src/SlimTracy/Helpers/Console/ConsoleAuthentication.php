<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Authenticate console credentials and stateless tokens. */
final readonly class ConsoleAuthentication
{
    /**
     * @param array<string, string> $accounts Usernames mapped to configured password hashes.
     *
     * Preserve the public hash-algorithm parameter name for named arguments.
     *
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        private array $accounts = [],
        private bool $noLogin = false,
        private string $passwordHashAlgorithm = ''
    ) {
    }

    /** Authenticate credentials and issue the legacy token format. */
    public function login(string $user, string $password): string
    {
        return (new ConsoleCredentials($this->accounts, $this->passwordHashAlgorithm))->login($user, $password);
    }

    /** Resolve the token's user, or an anonymous user when explicitly configured. */
    public function user(string $token): string
    {
        if ($this->noLogin) {
            return '';
        }

        return (new ConsoleSessionToken($this->accounts))->user($token);
    }
}

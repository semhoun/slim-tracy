<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

use SlimTracy\Exceptions\IncorrectUserOrPassword;

/** Validate a previously issued token against the current account credentials. */
final readonly class ConsoleSessionToken
{
    /** @param array<string, string> $accounts */
    public function __construct(private array $accounts)
    {
    }

    /** Resolve an authenticated token's user. */
    public function user(string $token): string
    {
        [$user, $digest] = array_pad(explode(':', trim($token), 2), 2, '');
        $user = trim($user);
        $stored = $this->accounts[$user] ?? '';
        if ($stored === '' || ! hash_equals(hash('sha256', $stored), trim($digest))) {
            throw new IncorrectUserOrPassword();
        }

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Helpers\Console;

use RuntimeException;

/** Example public service. */
final class RpcTestService
{
    /** @var int Number of successful calls to the public addition service. */
    public int $calls = 0;

    /**
     * Add two integers.
     * @param int $left first operand
     * @param int $right second operand
     * @return int sum
     */
    public function add(int $left, int $right = 1): int
    {
        ++$this->calls;
        return $left + $right;
    }

    /** Throw a service exception to exercise the RPC error envelope. */
    public function fail(): never
    {
        throw new RuntimeException('application error', -32603);
    }

    /** Provide a protected method which discovery and invocation must reject. */
    protected function protectedSecret(): string
    {
        return 'protected password';
    }

    /**
     * Deliberately uncalled: reflection must discover its private visibility and reject RPC access.
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function secret(): string
    {
        return 'private password';
    }
}

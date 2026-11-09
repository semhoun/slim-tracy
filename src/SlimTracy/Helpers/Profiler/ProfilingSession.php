<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Profiler;

use Closure;
use SlimTracy\Helpers\Profiler\Exception\EmptyStack;

/**
 * Owns nested measurements and the result processor for one profiling session.
 *
 * @internal
 */
final class ProfilingSession
{
    /**
     * @var list<Profile>
     */
    private array $stack = [];

    /**
     * @var Closure(Profile): Profile
     */
    private readonly Closure $postProcessor;

    /**
     * @param (callable(Profile): Profile)|null $postProcessor Transform each completed measurement.
     */
    public function __construct(?callable $postProcessor = null)
    {
        $processor = $postProcessor ?? static fn (Profile $profile): Profile => $profile;
        $this->postProcessor = $processor(...);
    }

    /** Push a measurement onto the active nesting stack. */
    public function start(Profile $profile): void
    {
        $this->stack[] = $profile;
    }

    /** Complete the innermost measurement and exclude its totals from its parent. */
    public function finish(?string $label, float $now, int $memoryUsage): Profile
    {
        if ($this->stack === []) {
            throw new EmptyStack('The stack is empty. Call start() first.');
        }

        $profile = array_pop($this->stack);
        $profile->complete($label, $now, $memoryUsage);
        if ($this->stack !== []) {
            $this->stack[array_key_last($this->stack)]->excludeChild($profile);
        }

        return ($this->postProcessor)($profile);
    }
}

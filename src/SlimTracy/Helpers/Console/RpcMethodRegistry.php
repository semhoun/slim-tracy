<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Resolve only public service methods, never infrastructure or non-public extension points. */
final readonly class RpcMethodRegistry
{
    private const HIDDEN = ['execute', '__construct', 'registerinstance', 'getinstances'];

    /** @param array<array-key, object> $instances */
    public function __construct(private array $instances)
    {
    }

    /** Resolve an authorized invocation handle, rejecting non-public methods. */
    public function resolve(string $fullMethod): ?RpcMethod
    {
        $parts = explode('.', $fullMethod, 2);
        $name = array_pop($parts);
        $namespace = array_pop($parts) ?? '';
        $instance = $this->instances[$namespace] ?? null;
        if ($instance === null || ! method_exists($instance, $name) || self::isHidden($name)) {
            return null;
        }

        $method = new \ReflectionMethod($instance, $name);
        // Reflection can invoke non-public methods on PHP 8.1+, so visibility must be checked explicitly.
        return $method->isPublic() ? new RpcMethod($instance, $method) : null;
    }

    /** Identify infrastructure methods excluded from RPC and discovery. */
    public static function isHidden(string $name): bool
    {
        return in_array(strtolower($name), self::HIDDEN, true);
    }
}

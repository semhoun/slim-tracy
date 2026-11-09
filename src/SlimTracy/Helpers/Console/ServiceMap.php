<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Build a Dojo SMD description from explicitly registered public services. */
final class ServiceMap
{
    /**
     * @param array<array-key, object> $instances Service objects keyed by their RPC namespaces.
     *
     * @return array<string, array|string>
     */
    public function describe(array $instances, string $target = ''): array
    {
        $result = [
            'transport' => 'POST', 'envelope' => 'JSON-RPC-2.0', 'SMDVersion' => '2.0',
            'contentType' => 'application/json', 'target' => $target, 'services' => [], 'description' => '',
        ];
        foreach ($instances as $namespace => $instance) {
            $class = new \ReflectionClass($instance);
            $description = (new ServiceDocumentation())->description((string) $class->getDocComment());
            $result['description'] .= $description === '' ? '' : $description . PHP_EOL;
            $result['services'] = array_replace($result['services'], $this->services($class, (string) $namespace));
        }

        return $result;
    }

    /**
     * Apply the registry's shared stateless visibility policy to described methods.
     *
     * @return array<string, array<string, array|string>>
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function services(\ReflectionClass $class, string $namespace): array
    {
        $services = [];
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (RpcMethodRegistry::isHidden($method->getName())) {
                continue;
            }

            $name = ltrim($namespace . '.' . $method->getName(), '.');
            $services[$name] = (new ServiceMethodDescription())->describe($method);
        }

        return $services;
    }
}

<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Describe one public service's parameters, documentation and return value for SMD. */
final class ServiceMethodDescription
{
    /** @return array<string, array|string> */
    public function describe(\ReflectionMethod $method): array
    {
        $comment = (string) $method->getDocComment();
        $service = ['parameters' => (new ServiceParameters())->describe($method, $comment)];
        $description = (new ServiceDocumentation())->description($comment);
        if ($description !== '') {
            $service['description'] = $description;
        }

        if (preg_match('/@return\s+([^\s]+)\s*([^\n\*]+)/', $comment, $matches)) {
            $service['returns'] = array_filter(['type' => $matches[1], 'description' => trim($matches[2])]);
        }

        return $service;
    }
}

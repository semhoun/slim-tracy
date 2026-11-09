<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Reflect service parameters and optional documentation for SMD discovery. */
final class ServiceParameters
{
    /** @return list<array<string, array|bool|float|int|\stdClass|string|null>> */
    public function describe(\ReflectionMethod $method, string $comment): array
    {
        $comments = $this->comments($comment);
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $param = ['name' => $name, 'optional' => $parameter->isDefaultValueAvailable()];
            $param += $comments[$name] ?? [];
            if ($param['optional']) {
                $param['default'] = $parameter->getDefaultValue();
            }

            $parameters[] = $param;
        }

        return $parameters;
    }

    /** @return array<string, array<string, string>> */
    private function comments(string $comment): array
    {
        $parameters = [];
        preg_match_all('/@param\s+([^\s]*)\s+([^\s]*)\s*([^\n\*]*)/', $comment, $matches);
        foreach ($matches[2] as $number => $name) {
            $parameters[trim($name, '$')] = array_filter([
                'type' => $matches[1][$number], 'description' => $matches[3][$number],
            ]);
        }

        return $parameters;
    }
}

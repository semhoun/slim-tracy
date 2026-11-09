<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/** Execute commands for an already authenticated console session. */
final class ConsoleCommand
{
    /** Execute a shell command, merging stderr into the returned output. */
    public function run(string $command): string
    {
        if ($command === '') {
            return '';
        }

        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
        if (! is_resource($process)) {
            return 'Can not execute command.';
        }

        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);
        return str_ends_with($output, "\n") ? substr($output, 0, -1) : $output;
    }
}

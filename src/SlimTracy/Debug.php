<?php

declare(strict_types=1);

namespace SlimTracy;

use Tracy\Debugger;

/** Autoloaded debugging shortcuts without global functions. */
final class Debug
{
    /** Prevent instances of this static debugging facade. */
    private function __construct()
    {
    }

    /**
     * Dump any value and terminate at the caller's location.
     *
     * Tracy exposes dumping statically; termination is this shortcut's explicit contract.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public static function dumpAndExit(mixed $value = ''): never
    {
        Debugger::dump($value);
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        exit('-- im in ' . $trace[0]['file'] . ', line: ' . $trace[0]['line'] . ' --');
    }

    /**
     * Start a named timer or return its elapsed time through Tracy's static API.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public static function timer(?string $name = ''): float
    {
        return Debugger::timer($name);
    }

    /** Render a JSON-serializable value in the browser console. */
    public static function console(
        mixed $value,
        ConsoleLevel $level = ConsoleLevel::Info
    ): void {
        $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        printf("<script>console.%s(%s);\n</script>", $level->value, $json);
    }
}

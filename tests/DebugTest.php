<?php

declare(strict_types=1);

namespace Tests;

use JsonException;
use PHPUnit\Framework\TestCase;
use SlimTracy\ConsoleLevel;
use SlimTracy\Debug;

final class DebugTest extends TestCase
{
    public function testEveryConsoleLevelRendersTheSelectedMethod(): void
    {
        foreach (ConsoleLevel::cases() as $level) {
            ob_start();
            Debug::console(['value' => 42], $level);
            $this->assertSame(
                '<script>console.' . $level->value . '({"value":42});' . "\n</script>",
                ob_get_clean()
            );
        }
    }

    public function testConsoleValuesCannotCloseTheScriptElement(): void
    {
        ob_start();
        Debug::console('</script><script>alert(1)</script>');
        $output = ob_get_clean();
        $this->assertSame(1, substr_count($output, '</script>'));
        $this->assertStringContainsString('\\u003C\\/script\\u003E', $output);
    }

    public function testConsoleRejectsInvalidJsonWithoutPartialOutput(): void
    {
        ob_start();
        try {
            Debug::console("\xB1");
            $this->fail('Invalid UTF-8 should be rejected.');
        } catch (JsonException $exception) {
            $this->assertSame(JSON_ERROR_UTF8, $exception->getCode());
            $this->assertSame('', ob_get_contents());
        } finally {
            ob_end_clean();
        }
    }

    public function testTimerCanBeStartedAndRead(): void
    {
        $this->assertSame(0.0, Debug::timer('debug-test'));
        $this->assertGreaterThanOrEqual(0.0, Debug::timer('debug-test'));
    }

    public function testDumpAndExitWritesTheValueAndCallSite(): void
    {
        $autoload = var_export(dirname(__DIR__) . '/vendor/autoload.php', true);
        $code = 'require ' . $autoload . '; SlimTracy\\Debug::dumpAndExit("dump-marker");';
        $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $errors);
        $this->assertStringContainsString('dump-marker', $output);
        $this->assertStringContainsString('-- im in Command line code, line: 1 --', $output);
    }
}

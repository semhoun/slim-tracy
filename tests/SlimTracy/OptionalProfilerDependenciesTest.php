<?php

declare(strict_types=1);

namespace Tests\SlimTracy;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SlimTracy\Profiling\StructuredExporter;
use Tracy\Debugger;

#[RunTestsInSeparateProcesses]
final class OptionalProfilerDependenciesTest extends TestCase
{
    public function testExportWorksWithoutAnyOptionalIntegrationAutoloader(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            spl_autoload_unregister($loader);
        }
        $libraryOnly = static function (string $class): void {
            if (str_starts_with($class, 'SlimTracy\\')) {
                require dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', $class) . '.php';
            }
        };
        spl_autoload_register($libraryOnly);
        try {
            $optionalAvailable = class_exists('Doctrine\\DBAL\\Connection')
                || class_exists('Illuminate\\Database\\Capsule\\Manager')
                || class_exists('Twig\\Profiler\\Profile');
            $export = (new StructuredExporter(null, [
                'configs' => ['AjaxProfiler' => ['enabled' => true]],
            ]))->export();
        } finally {
            spl_autoload_unregister($libraryOnly);
            foreach ($loaders as $loader) {
                spl_autoload_register($loader);
            }
        }
        self::assertFalse($optionalAvailable);
        self::assertSame([], $export);
    }
}

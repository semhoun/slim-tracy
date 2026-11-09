<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    // Keep explicit ternaries required by PHP Insights; avoid contradictory autofixes.
    ->withSkip([
        \Rector\Php53\Rector\Ternary\TernaryToElvisRector::class,
        // These parameters are consumed by the included panel template, not unused.
        \Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector::class => [
            __DIR__ . '/src/SlimTracy/Helpers/VendorVersionsPanel.php',
        ],
    ])
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPhpSets(
        php82: true
    )
    ->withComposerBased(
        twig: true,
        doctrine: true,
    )
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        privatization: true,
        typeDeclarations: true,
        instanceOf: true,
        earlyReturn: true,
    );

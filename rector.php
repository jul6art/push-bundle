<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\PHPUnit120\Rector\Class_\AllowMockObjectsForDataProviderRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/PushBundle.php',
        __DIR__.'/Attribute',
        __DIR__.'/DependencyInjection',
        __DIR__.'/Dispatcher',
        __DIR__.'/EventListener',
        __DIR__.'/Factory',
        __DIR__.'/Message',
        __DIR__.'/MessageHandler',
        __DIR__.'/Service',
        __DIR__.'/Tests',
    ])
    // No argument: the target PHP version is read from the "php" constraint in
    // composer.json, so the rule set follows the bundle instead of drifting.
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withAttributesSets(symfony: true, doctrine: true, phpunit: true)
    ->withComposerBased(doctrine: true, symfony: true, phpunit: true)
    ->withSkip([
        // Pure helpers are deliberately static: it documents that they touch no state.
        LocallyCalledStaticMethodToNonStaticRector::class,
        // Adds #[AllowMockObjectsWithoutExpectations] to data-provider tests that use
        // no mock at all, so the attribute would suppress a check nothing triggers.
        AllowMockObjectsForDataProviderRector::class,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);

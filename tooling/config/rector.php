<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../../src/core',
        __DIR__ . '/../../src/cli',
        __DIR__ . '/../../tests',
    ])
    ->withSkip([
        __DIR__ . '/../../vendor',
        __DIR__ . '/../../build',
    ])
    ->withPhpVersion(\Rector\ValueObject\PhpVersion::PHP_74)
    ->withSets([
        LevelSetList::UP_TO_PHP_74,
    ])
    ->withRules([
        InlineConstructorDefaultToPropertyRector::class,
    ])
;

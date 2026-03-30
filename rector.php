<?php

use Rector\Caching\ValueObject\Storage\MemoryCacheStorage;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;

return RectorConfig::configure()
    ->withSkip([
        //    __DIR__ . '/src/Controller/*'
    ])
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        RemoveNonExistingVarAnnotationRector::class,    // not compatible with phpstan
    ])
    ->withAttributesSets()
    ->withPhp74Sets()
    // >withPreparedSets(typeDeclarations: true)
      ->withTypeCoverageLevel(100)
     // withPreparedSets(deadCode: true)
     ->withDeadCodeLevel(100)
    // withPreparedSets(codeQuality: true)
     ->withCodeQualityLevel(100)
    // withCache(cacheClas: MemoryCacheStorage::class)
;

<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
 ->withPaths([
     __DIR__ . '/src',
     __DIR__ . '/tests',
 ])
    ->withPreparedSets(symfonyCodeQuality: true)
    ->withComposerBased(symfony: true);

<?php

use Rector\Config\RectorConfig;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withPaths( // here we can define, which directories will be processed
        [
            __DIR__.'/app',
            __DIR__.'/config',
            __DIR__.'/database',
            __DIR__.'/routes',
            __DIR__.'/tests']
    )
    ->withAutoloadPaths([
        __DIR__.'/app',
    ])
    // register single rule
    ->withRules([
    ])
    ->withSkip([
        DisallowedEmptyRuleFixerRector::class,
    ])
    // here we can define, what prepared sets of rules will be applied
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    );

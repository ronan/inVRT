<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['vendor', 'build', 'coverage', 'tests/fixtures'])
    ->notPath('node_modules')
    ;
    
return (new PhpCsFixer\Config())
    ->setCacheFile(__DIR__.'/.php-cs-fixer.cache')
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS' => true,
        '@PER-CS:risky' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['const', 'function', 'class'],
            'sort_algorithm' => 'alpha',
        ],
        'phpdoc_to_comment' => false,
    ])
    ->setFinder($finder)
;

<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->exclude('var')
    ->exclude('vendor');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'single_line_after_imports' => true,
        'phpdoc_order' => true,
        'phpdoc_summary' => false,
        'phpdoc_align' => true,
        'yoda_style' => false,
    ])
    ->setFinder($finder);
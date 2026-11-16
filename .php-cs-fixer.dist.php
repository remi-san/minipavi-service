<?php

// Coding style of the framework: PER Coding Style, with short arrays and sorted imports.
// composer cs: checks it, composer cs-fix: fixes it.

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->append([__DIR__.'/bootstrap.php', __FILE__]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
    ])
    ->setFinder($finder);

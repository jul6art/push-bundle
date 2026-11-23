<?php

declare(strict_types=1);

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude('vendor')
    ->append([
        __FILE__,
        __DIR__.'/rector.php',
    ]);

return new PhpCsFixer\Config()
    // PHP CS Fixer knows PHP syntax up to 8.5 (v3.95.32): on PHP 8.6 it runs only when allowed. The code holds no syntax
    // of 8.6 of its own; drop this line once a release supports 8.6.
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP8x5Migration' => true,
        '@PHP8x5Migration:risky' => true,
        'declare_strict_types' => true,
        // Keeps the leading backslash on native calls, as the bundle sources do.
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'ordered_class_elements' => [
            'order' => ['use_trait', 'case', 'constant', 'property', 'construct', 'destruct', 'magic', 'phpunit', 'method'],
        ],
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
    ]);

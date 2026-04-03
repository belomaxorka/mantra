<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/core',
        __DIR__ . '/modules',
        __DIR__ . '/themes',
        __DIR__ . '/tests',
    ])
    ->append([
        __DIR__ . '/index.php',
        __DIR__ . '/install.php',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // PHP version target
        '@PHP81Migration' => true,
        '@PHP80Migration:risky' => true,

        // Array syntax (already converted, enforce going forward)
        'array_syntax' => ['syntax' => 'short'],

        // Modern string functions
        'modernize_strpos' => true,

        // Casting
        'modernize_types_casting' => true,
        'short_scalar_cast' => true,

        // Clean up
        'no_alias_functions' => true,
        'no_unused_imports' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'single_blank_line_at_eof' => true,

        // Trailing commas
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],

        // Spacing
        'binary_operator_spaces' => ['default' => 'single_space'],
        'concat_space' => ['spacing' => 'one'],

        // Braces / structure — don't enforce, project has its own style
        'braces_position' => false,
        'curly_braces_position' => false,
        'control_structure_braces' => false,
        'control_structure_continuation_position' => false,
        'statement_indentation' => false,
        'method_argument_space' => false,

        // Don't force changes that would create huge diffs
        'visibility_required' => false,
        'class_definition' => false,
        'single_class_element_per_statement' => false,
        'ordered_imports' => false,
    ])
    ->setFinder($finder);

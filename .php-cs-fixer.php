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
        'cast_spaces' => ['space' => 'none'],
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

        // Lock in existing conventions (zero-diff)
        'single_quote' => true,
        'no_extra_blank_lines' => true,
        'yoda_style' => false,
        'magic_constant_casing' => true,
        'magic_method_casing' => true,
        'native_function_casing' => true,
        'no_empty_statement' => true,
        'standardize_not_equals' => true,
        'normalize_index_brace' => true,
        'elseif' => true,
        'is_null' => true,

        // Minor cleanups (minimal diff)
        'no_spaces_around_offset' => true,
        'trim_array_spaces' => true,
        'object_operator_without_whitespace' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => true,
        'return_type_declaration' => true,
        'compact_nullable_type_declaration' => true,
        'no_unneeded_control_parentheses' => true,
        'declare_equal_normalize' => true,
        'ternary_operator_spaces' => true,
        'clean_namespace' => true,
        'lambda_not_used_import' => true,
        'no_unneeded_import_alias' => true,
        'include' => true,
        'type_declaration_spaces' => true,
        'no_blank_lines_after_class_opening' => true,
        'single_line_after_imports' => true,
        'assign_null_coalescing_to_coalesce_equal' => true,

        // Modernization (noticeable but safe diff)
        'ternary_to_null_coalescing' => true,
        'strict_comparison' => true,

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

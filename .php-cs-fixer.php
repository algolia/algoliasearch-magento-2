<?php

/** @var PhpCsFixer\Config $config */
$distConfig = getcwd() . '/.php-cs-fixer.dist.php';
if (!file_exists($distConfig)) {
    throw new RuntimeException(sprintf('Could not find .php-cs-fixer.dist.php in %s', getcwd()));
}
$config = require $distConfig;

$originalRules = $config->getRules();

$extensionRules = [
    'blank_line_after_opening_tag' => true,
    'blank_line_before_statement' => true,
    'cast_spaces' => true,
    'combine_consecutive_unsets' => true,
    'mb_str_functions' => true,
    'no_blank_lines_after_class_opening' => true,
    'no_empty_phpdoc' => true,
    'no_empty_statement' => true,
    'no_multiline_whitespace_around_double_arrow' => true,
    'no_short_bool_cast' => true,
    'echo_tag_syntax' => true,
    'no_superfluous_phpdoc_tags' => true,
    'no_unneeded_control_parentheses' => true,
    'no_unreachable_default_argument_value' => true,
    'no_unset_on_property' => true,
    'no_unused_imports' => true,
    'no_useless_else' => true,
    'no_useless_return' => true,
    'no_whitespace_before_comma_in_array' => true,
    'no_whitespace_in_blank_line' => true,
    'normalize_index_brace' => true,
    'not_operator_with_space' => false,
    'object_operator_without_whitespace' => true,
    'phpdoc_annotation_without_dot' => true,
    'phpdoc_line_span' => ['const' => 'single', 'property' => 'multi', 'method' => 'multi'],
    'general_phpdoc_tag_rename' => true,
    'phpdoc_inline_tag_normalizer' => true,
    'phpdoc_tag_type' => true,
    'phpdoc_order' => true,
    'phpdoc_scalar' => true,
    'phpdoc_separation' => true,
    'phpdoc_single_line_var_spacing' => true,
    'protected_to_private' => true,
    'psr_autoloading' => true,
    'short_scalar_cast' => true, 
    'blank_lines_before_namespace' => true,
    'single_quote' => true,
    'space_after_semicolon' => true,
    'standardize_not_equals' => true,
    'ternary_operator_spaces' => true,
    'trailing_comma_in_multiline' => true,
    'trim_array_spaces' => true,
];

return $config->setRules(array_merge($originalRules, $extensionRules));

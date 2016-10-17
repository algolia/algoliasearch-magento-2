<?php

define('DS', DIRECTORY_SEPARATOR);

$directories = [
    __DIR__.DS.'Adapter',
    __DIR__.DS.'Block',
    __DIR__.DS.'Controller',
    __DIR__.DS.'Helper',
    __DIR__.DS.'Model',
    __DIR__.DS.'Plugin',
];

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->in($directories);

return Symfony\CS\Config\Config::create()
   ->finder($finder)
   ->level(Symfony\CS\FixerInterface::PSR2_LEVEL)
   ->fixers([
       'double_arrow_multiline_whitespaces',
       'duplicate_semicolon',
       'extra_empty_lines',
       'include',
       'join_function',
       'multiline_array_trailing_comma',
       'namespace_no_leading_whitespace',
       'new_with_braces',
       'object_operator',
       'operators_spaces',
       'remove_leading_slash_use',
       'remove_lines_between_uses',
       'single_array_no_trailing_comma',
       'spaces_before_semicolon',
       'standardize_not_equal',
       'ternary_spaces',
       'unused_use',
       'whitespacy_lines',
       'concat_with_spaces',
       'multiline_spaces_before_semicolon',
       'ordered_use',
       'short_array_syntax',
   ]);
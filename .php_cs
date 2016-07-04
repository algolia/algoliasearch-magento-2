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
    ->setUsingCache(true)
    ->level(Symfony\CS\FixerInterface::SYMFONY_LEVEL)
    ->fixers([
        'align_double_arrow',
        'short_array_syntax',
        '-multiline_array_trailing_comma',
        '-pre_increment',
        '-phpdoc_short_description',
    ])->finder($finder);
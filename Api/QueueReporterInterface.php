<?php

namespace Algolia\AlgoliaSearch\Api;

/**
 * Semantic output channel for queue progress reporting, decoupled from the presentation layer.
 * Implementations decide how each severity is rendered (console decoration, log levels, ...).
 */
interface QueueReporterInterface
{
    public function info(string $message): void;

    public function warning(string $message): void;

    public function error(string $message): void;

    public function log(string $message): void;
}

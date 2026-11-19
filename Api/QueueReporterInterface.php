<?php

namespace Algolia\AlgoliaSearch\Api;

/**
 * Semantic output channel for queue progress reporting, decoupled from the presentation layer.
 * Implementations decide how each severity is rendered (console decoration, log levels, ...).
 */
interface QueueReporterInterface
{
    /**
     * Report a successfully completed step or other neutral progress information.
     */
    public function info(string $message): void;

    /**
     * Report a non-fatal condition worth surfacing: skipped work, degraded behavior, retryable problems.
     */
    public function warning(string $message): void;

    /**
     * Report a failure the operator must act on: exceptions, exhausted retries, jobs that cannot run.
     */
    public function error(string $message): void;

    /**
     * Relay a pre-formatted line produced elsewhere (e.g. output of a worker child process) without
     * interpreting it: the message is passed through as-is, formatter tags included. Callers must
     * not use log() for their own severity-classified messages; use info()/warning()/error().
     */
    public function log(string $message): void;
}

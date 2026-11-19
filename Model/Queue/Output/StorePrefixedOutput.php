<?php

namespace Algolia\AlgoliaSearch\Model\Queue\Output;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;

/**
 * Buffers a worker's stdout/stderr chunks and relays each complete line to the queue
 * reporter prefixed with a per-store label, so interleaved worker streams stay
 * attributable in the parent's output. Partial lines are buffered until a newline
 * arrives (workers are long-lived, so one instance must serve a worker for its whole
 * lifespan); flushTail() emits whatever is left when the worker exits without a
 * trailing newline.
 */
class StorePrefixedOutput
{
    protected string $lineBuffer = '';

    public function __construct(
        protected QueueReporterInterface $reporter,
        protected string $label,
    ) {}

    public function write(string $message, bool $newline = false): void
    {
        $this->lineBuffer .= $message;

        if ($newline) {
            $this->lineBuffer .= "\n";
        }

        $this->emitCompleteLines();
    }

    public function writeln(string $message): void
    {
        $this->write($message, true);
    }

    /**
     * Emits a buffered partial line when a worker ends without a trailing newline.
     */
    public function flushTail(): void
    {
        if ($this->lineBuffer === '') {
            return;
        }

        $tail = $this->stripTrailingCarriageReturn($this->lineBuffer);
        $this->lineBuffer = '';
        $this->reporter->log($this->prefix($tail));
    }

    protected function emitCompleteLines(): void
    {
        while (($newlinePosition = strpos($this->lineBuffer, "\n")) !== false) {
            $line = substr($this->lineBuffer, 0, $newlinePosition);
            $this->lineBuffer = substr($this->lineBuffer, $newlinePosition + 1);
            $this->reporter->log($this->prefix($this->stripTrailingCarriageReturn($line)));
        }
    }

    protected function prefix(string $line): string
    {
        return '[' . $this->label . '] ' . $line;
    }

    /**
     * A trailing \r is part of a \r\n line break, not content; it may also end up in the
     * buffer when a \r\n pair is split across read chunks.
     */
    protected function stripTrailingCarriageReturn(string $line): string
    {
        return str_ends_with($line, "\r") ? substr($line, 0, -1) : $line;
    }
}

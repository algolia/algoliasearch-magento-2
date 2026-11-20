<?php

namespace Algolia\AlgoliaSearch\Model\Queue\Output;

/**
 * Line splitter for a worker's streamed output: push() accepts raw read chunks and
 * returns the complete lines they contain, buffering the partial tail between polls
 * (workers are long-lived, so one instance must serve a worker for its whole lifespan).
 * flush() returns whatever is left when the worker exits without a trailing newline.
 * Purely mechanical: no labels, no output side effects; the pool prefixes and relays.
 */
class WorkerLineBuffer
{
    protected string $lineBuffer = '';

    /**
     * Push a chunk into the buffer and return completed lines.
     * Incomplete lines remain in the buffer.
     *
     * @return string[] Complete lines (without their line breaks) contained in the chunk
     */
    public function push(string $chunk): array
    {
        $this->lineBuffer .= $chunk;

        $lines = [];
        while (($newlinePosition = strpos($this->lineBuffer, "\n")) !== false) {
            $lines[] = $this->stripTrailingCarriageReturn(substr($this->lineBuffer, 0, $newlinePosition));
            $this->lineBuffer = substr($this->lineBuffer, $newlinePosition + 1);
        }

        return $lines;
    }

    /**
     * Get any remaining output buffer contents once the process completes.
     *
     * @return string|null
     */
    public function flush(): ?string
    {
        if ($this->lineBuffer === '') {
            return null;
        }

        $tail = $this->stripTrailingCarriageReturn($this->lineBuffer);
        $this->lineBuffer = '';

        return $tail;
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

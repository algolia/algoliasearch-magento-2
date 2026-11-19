<?php

namespace Algolia\AlgoliaSearch\Model\Queue\Output;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Decorator that prefixes each complete line of a worker's stdout/stderr with a per-store
 * label, so interleaved worker streams stay attributable in the parent's output. Partial
 * lines are buffered until a newline arrives (workers are long-lived, so one decorator
 * instance must serve a worker for its whole lifespan); flushTail() emits whatever is
 * left when the worker exits without a trailing newline.
 */
class StorePrefixedOutput implements OutputInterface
{
    protected string $lineBuffer = '';

    public function __construct(
        protected OutputInterface $inner,
        protected string $label,
    ) {}

    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        foreach (is_iterable($messages) ? $messages : [$messages] as $message) {
            $this->lineBuffer .= $message;

            if ($newline) {
                $this->lineBuffer .= "\n";
            }

            $this->emitCompleteLines();
        }
    }

    public function writeln(string|iterable $messages, int $options = 0): void
    {
        $this->write($messages, true, $options);
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
        $this->inner->writeln($this->prefix($tail));
    }

    protected function emitCompleteLines(): void
    {
        while (($newlinePosition = strpos($this->lineBuffer, "\n")) !== false) {
            $line = substr($this->lineBuffer, 0, $newlinePosition);
            $this->lineBuffer = substr($this->lineBuffer, $newlinePosition + 1);
            $this->inner->writeln($this->prefix($this->stripTrailingCarriageReturn($line)));
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

    public function isDecorated(): bool
    {
        return false;
    }

    public function setDecorated(bool $decorated): void
    {
    }

    public function getVerbosity(): int
    {
        return self::VERBOSITY_NORMAL;
    }

    public function setVerbosity(int $level): void
    {
    }

    public function isQuiet(): bool
    {
        return false;
    }

    public function isVerbose(): bool
    {
        return false;
    }

    public function isVeryVerbose(): bool
    {
        return false;
    }

    public function isDebug(): bool
    {
        return false;
    }

    public function getFormatter(): OutputFormatterInterface
    {
        return $this->inner->getFormatter();
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        $this->inner->setFormatter($formatter);
    }
}

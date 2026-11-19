<?php

namespace Algolia\AlgoliaSearch\Model\Queue\Output;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * OutputInterface adapter that forwards worker output lines to a queue reporter, so
 * cron-triggered orchestrator runs capture child stdout/stderr through the same reporter
 * as the orchestrator's own lines (algolia.log at info level via LoggerQueueReporter).
 * Receives complete, already-prefixed lines from StorePrefixedOutput.
 */
class ReporterWorkerOutput implements OutputInterface
{
    public function __construct(
        protected QueueReporterInterface $reporter,
    ) {}

    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        $this->forward($messages);
    }

    public function writeln(string|iterable $messages, int $options = 0): void
    {
        $this->forward($messages);
    }

    protected function forward(string|iterable $messages): void
    {
        foreach (is_iterable($messages) ? $messages : [$messages] as $message) {
            $this->reporter->log($message);
        }
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
        return new OutputFormatter();
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
    }
}

<?php

namespace Algolia\AlgoliaSearch\Logger;

use Algolia\AlgoliaSearch\Api\LoggerInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console output sink for cron context, where there is no console attached: each message
 * is forwarded to the Algolia log as one entry with decoration tags stripped, so
 * <info>/<error> markup never reaches algolia.log raw.
 */
class CronLoggerOutput implements OutputInterface
{
    public function __construct(
        protected LoggerInterface $logger
    ) {}

    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        $this->log($messages);
    }

    public function writeln(string|iterable $messages, int $options = 0): void
    {
        $this->log($messages);
    }

    public function setVerbosity(int $level): void {}

    public function getVerbosity(): int
    {
        return OutputInterface::VERBOSITY_NORMAL;
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

    public function setDecorated(bool $decorated): void {}

    public function isDecorated(): bool
    {
        return false;
    }

    public function setFormatter(OutputFormatterInterface $formatter): void {}

    public function getFormatter(): OutputFormatterInterface
    {
        return new OutputFormatter();
    }

    protected function log(string|iterable $messages): void
    {
        foreach (is_iterable($messages) ? $messages : [$messages] as $message) {
            $message = (string) $message;

            if (str_contains($message, '<error>')) {
                $this->logger->error(strip_tags($message));
            } else {
                $this->logger->info(strip_tags($message));
            }
        }
    }
}

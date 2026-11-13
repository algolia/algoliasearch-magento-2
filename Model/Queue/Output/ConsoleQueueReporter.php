<?php

namespace Algolia\AlgoliaSearch\Model\Queue\Output;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleQueueReporter implements QueueReporterInterface
{
    public function __construct(
        protected OutputInterface $output
    ) {}

    public function info(string $message): void
    {
        $this->output->writeln('<info>' . $message . '</info>');
    }

    public function warning(string $message): void
    {
        $this->output->writeln('<comment>' . $message . '</comment>');
    }

    public function error(string $message): void
    {
        $this->output->writeln('<error>' . $message . '</error>');
    }

    public function log(string $message): void
    {
        $this->output->writeln($message);
    }
}

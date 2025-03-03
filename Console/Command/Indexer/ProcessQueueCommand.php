<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessQueueCommand extends AbstractIndexerCommand
{
    protected function getCommandName(): string
    {
        return 'process_queue';
    }

    protected function getCommandDescription(): string
    {
        return 'Process Algolia\'s indexing queue';
    }

    protected function getAdditionalDefinition(): array
    {
        return [];
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->input = $input;
        $this->setAreaCode();

        $storeIds = $this->getStoreIds($input);
        $output->writeln(
            $this->decorateOperationAnnouncementMessage(
                'Process Algolia\'s indexing queue for {{target}}',
                $storeIds
            )
        );

        return Cli::RETURN_SUCCESS;
    }
}

<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexAdditionalSectionsCommand extends AbstractIndexerCommand
{
    protected function getCommandName(): string
    {
        return 'additional_sections';
    }

    protected function getCommandDescription(): string
    {
        return 'Reindex additional sections to Algolia';
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
                'Reindexing additional sections for {{target}}',
                $storeIds
            )
        );

        return Cli::RETURN_SUCCESS;
    }
}

<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexSuggestionsCommand extends AbstractIndexerCommand
{
    protected function getCommandName(): string
    {
        return 'suggestions';
    }

    protected function getCommandDescription(): string
    {
        return 'Reindex suggestions to Algolia';
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
                'Reindexing suggestions for {{target}}',
                $storeIds
            )
        );

        return Cli::RETURN_SUCCESS;
    }
}

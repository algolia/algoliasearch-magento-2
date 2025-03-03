<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteProductsCommand extends AbstractIndexerCommand
{
    protected function getCommandName(): string
    {
        return 'delete_products';
    }

    protected function getCommandDescription(): string
    {
        return 'Delete unwanted products from Algolia product indicies';
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
                'Deleting unwanted products for {{target}}',
                $storeIds
            )
        );

        return Cli::RETURN_SUCCESS;
    }
}

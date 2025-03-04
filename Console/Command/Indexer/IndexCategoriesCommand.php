<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Algolia\AlgoliaSearch\Service\Category\QueueBuilder as CategoryQueueBuilder;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexCategoriesCommand extends AbstractIndexerCommand
{
    public function __construct(
        protected CategoryQueueBuilder $categoryQueueBuilder,
        protected StoreManagerInterface $storeManager,
        State $state,
        StoreNameFetcher $storeNameFetcher,
        ?string $name = null
    ) {
        parent::__construct($storeManager, $state, $storeNameFetcher, $name);
    }

    protected function getCommandName(): string
    {
        return 'categories';
    }

    protected function getCommandDescription(): string
    {
        return 'Reindex categories to Algolia';
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

        $storeIds = $this->getStoreIdsToIndex($input);

        foreach ($storeIds as $storeId) {
            $output->writeln('<info>Reindexing categories for ' . $this->storeNameFetcher->getStoreName($storeId)) . '</info>';
            $this->categoryQueueBuilder->buildQueue($storeId);
        }

        return Cli::RETURN_SUCCESS;
    }
}

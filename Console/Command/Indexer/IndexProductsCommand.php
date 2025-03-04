<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Service\Product\QueueBuilder as ProductQueueBuilder;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexProductsCommand extends AbstractIndexerCommand
{
    public function __construct(
        protected ProductQueueBuilder $productQueueBuilder,
        protected StoreManagerInterface $storeManager,
        State $state,
        StoreNameFetcher $storeNameFetcher,
        ?string $name = null
    ) {
        parent::__construct($storeManager, $state, $storeNameFetcher, $name);
    }

    protected function getCommandName(): string
    {
        return 'products';
    }

    protected function getCommandDescription(): string
    {
        return 'Reindex products to Algolia';
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
            $output->writeln('<info>Reindexing products for ' . $this->storeNameFetcher->getStoreName($storeId)) . '</info>';
            $this->productQueueBuilder->buildQueue($storeId);
        }

        return Cli::RETURN_SUCCESS;
    }
}

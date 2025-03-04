<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Algolia\AlgoliaSearch\Service\Product\QueueBuilder as ProductQueueBuilder;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteProductsCommand extends AbstractIndexerCommand
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
        return 'delete_products';
    }

    protected function getCommandDescription(): string
    {
        return 'Delete unwanted products from Algolia product indices';
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
            $output->writeln('<info>Deleting inactive products for ' . $this->storeNameFetcher->getStoreName($storeId)) . '</info>';
            $this->productQueueBuilder->deleteInactiveProducts($storeId);
        }

        return Cli::RETURN_SUCCESS;
    }
}

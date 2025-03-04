<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Service\Page\QueueBuilder as PageQueueBuilder;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexPagesCommand extends AbstractIndexerCommand
{
    public function __construct(
        protected PageQueueBuilder $pageQueueBuilder,
        protected StoreManagerInterface $storeManager,
        State $state,
        StoreNameFetcher $storeNameFetcher,
        ?string $name = null
    ) {
        parent::__construct($storeManager, $state, $storeNameFetcher, $name);
    }

    protected function getCommandName(): string
    {
        return 'pages';
    }

    protected function getCommandDescription(): string
    {
        return 'Reindex pages to Algolia';
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
            $output->writeln('<info>Reindexing pages for ' . $this->storeNameFetcher->getStoreName($storeId)) . '</info>';
            $this->pageQueueBuilder->buildQueue($storeId);
        }

        return Cli::RETURN_SUCCESS;
    }
}

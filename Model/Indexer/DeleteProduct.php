<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class DeleteProduct implements Magento\Framework\Indexer\ActionInterface, Magento\Framework\Mview\ActionInterface
{
    private $fullAction;
    private $storeManager;
    private $algoliaHelper;
    private $queue;
    private $configHelper;
    private $messageManager;
    private $output;

    public function __construct(
        StoreManagerInterface $storeManager,
        Data $helper,
        AlgoliaHelper $algoliaHelper,
        Queue $queue,
        ConfigHelper $configHelper,
        ManagerInterface $messageManager,
        ConsoleOutput $output
    ) {
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->algoliaHelper = $algoliaHelper;
        $this->queue = $queue;
        $this->configHelper = $configHelper;
        $this->messageManager = $messageManager;
        $this->output = $output;
    }

    public function execute($ids)
    {
        return $this;
    }

    public function executeFull()
    {
        if (!$this->configHelper->getApplicationID()
            || !$this->configHelper->getAPIKey()
            || !$this->configHelper->getSearchOnlyAPIKey()) {
            $errorMessage = 'Algolia reindexing failed: 
                You need to configure your Algolia credentials in Stores > Configuration > Algolia Search.';

            if (php_sapi_name() === 'cli') {
                $this->output->writeln($errorMessage);

                return;
            }

            $this->messageManager->addErrorMessage($errorMessage);

            return;
        }

        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            if ($this->fullAction->isIndexingEnabled($storeId) === false) {
                continue;
            }

            $this->fullAction->deleteInactiveProducts($storeId);
        }
    }

    public function executeList(array $ids)
    {
        return $this;
    }

    public function executeRow($id)
    {
        return $this;
    }
}

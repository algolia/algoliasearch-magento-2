<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento;
use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class CategoryProductsOrder implements ActionInterface, Magento\Framework\Mview\ActionInterface
{
    private $storeManager;
    private $fullAction;
    private $queue;
    private $configHelper;
    private $messageManager;
    private $output;

    public static $affectedProductIds = [];

    public function __construct(
        StoreManagerInterface $storeManager,
        Data $helper,
        Queue $queue,
        ConfigHelper $configHelper,
        ManagerInterface $messageManager,
        ConsoleOutput $output
    ) {
    
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->queue = $queue;
        $this->configHelper = $configHelper;
        $this->messageManager = $messageManager;
        $this->output = $output;
    }

    public function execute($categoryIds)
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

            $this->queue->addToQueue(
                $this->fullAction,
                'rebuildCategoryProductsOrderQueryRules',
                ['store_id' => $storeId, 'category_ids' => $categoryIds],
                count($categoryIds)
            );
        }
    }

    public function executeFull()
    {
        $this->execute(null);
    }

    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    public function executeRow($id)
    {
        $this->execute([$id]);
    }
}

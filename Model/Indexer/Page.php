<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Service\Page\BatchQueueProcessor as PageBatchQueueProcessor;

/**
 * This indexer is now disabled by default, prefer use the `bin/magento algolia:reindex:pages` command instead
 * If you want to re-enable it, you can do it in the Magento configuration ("Algolia Search > Indexing Manager" section)
 */
class Page implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public function __construct(
        protected PageHelper $pageHelper,
        protected ConfigHelper $configHelper,
        protected PageBatchQueueProcessor $pageBatchQueueProcessor
    ) {}

    /**
     * @param $ids
     * @return void
     */
    public function execute($ids)
    {
        if (!$this->configHelper->isPagesIndexerEnabled()) {
            return;
        }

        foreach ($this->pageHelper->getStores() as $storeId) {
            $this->pageBatchQueueProcessor->processBatch($storeId, $ids);
        }
    }

    /**
     * @return void
     */
    public function executeFull()
    {
        $this->execute(null);
    }

    /**
     * @param array $ids
     * @return void
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @param $id
     * @return void
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }
}

<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Service\Page\QueueBuilder as PageQueueBuilder;


class Page implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public function __construct(
        protected PageHelper $pageHelper,
        protected ConfigHelper $configHelper,
        protected PageQueueBuilder $pageQueueBuilder
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
            $this->pageQueueBuilder->buildQueue($storeId, $ids);
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

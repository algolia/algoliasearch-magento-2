<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;

class PageObserver
{
    private $indexer;

    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexer = $indexerRegistry->get('algolia_pages');
    }

    public function beforeSave(
        \Magento\Cms\Model\ResourceModel\Page $pageResource,
        AbstractModel $page
    ) {
        $pageResource->addCommitCallback(function () use ($page) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($page->getId());
            }
        });

        return [$page];
    }

    public function beforeDelete(
        \Magento\Cms\Model\ResourceModel\Page $pageResource,
        AbstractModel $page
    ) {
        $pageResource->addCommitCallback(function () use ($page) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($page->getId());
            }
        });

        return [$page];
    }
}

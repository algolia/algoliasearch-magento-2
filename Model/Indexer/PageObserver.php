<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;

class PageObserver
{
    private $indexer;

    public function __construct(
        IndexerRegistry $indexerRegistry,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ) {
        $this->indexer = $indexerRegistry->get('algolia_pages');
    }

    public function beforeSave(
        \Magento\Cms\Model\ResourceModel\Page $pageResource,
        AbstractModel $page
    ) {
        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey()) {
            return [$page];
        }

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
        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey()) {
            return [$page];
        }

        $pageResource->addCommitCallback(function () use ($page) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($page->getId());
            }
        });

        return [$page];
    }
}

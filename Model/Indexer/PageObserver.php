<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;

class PageObserver
{
    private $indexer;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    public function __construct(
        IndexerRegistry $indexerRegistry,
        ConfigHelper $configHelper
    ) {
        $this->indexer = $indexerRegistry->get('algolia_pages');
        $this->configHelper = $configHelper;
    }

    public function beforeSave(
        \Magento\Cms\Model\ResourceModel\Page $pageResource,
        AbstractModel $page
    ) {
        if (!$this->configHelper->getApplicationID()
            || !$this->configHelper->getAPIKey()
            || !$this->configHelper->getSearchOnlyAPIKey()) {
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
        if (!$this->configHelper->getApplicationID()
            || !$this->configHelper->getAPIKey()
            || !$this->configHelper->getSearchOnlyAPIKey()) {
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

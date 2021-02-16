<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;

class PageObserver
{
    private $indexer;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var AdapterInterface
     */
    private $dbConnection;

    public function __construct(
        IndexerRegistry $indexerRegistry,
        ResourceConnection $resourceConnection
    ) {
        $this->indexer = $indexerRegistry->get('algolia_pages');
        $this->resourceConnection = $resourceConnection;
    }

    public function beforeSave(
        \Magento\Cms\Model\ResourceModel\Page $pageResource,
        AbstractModel $page
    ) {
        // On fresh Magento install, we have to make sure that category entity type exists
        if (!$this->categoryEntityTypeExists()) {
            return [$page];
        }

        $pageResource->addCommitCallback(function () use ($page) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($page->getId());
            }
        });

        return [$page];
    }

    protected function categoryEntityTypeExists()
    {
        $this->dbConnection = $this->resourceConnection->getConnection();

        $select = $this->dbConnection->select()
            ->from(
                $this->resourceConnection->getTableName('eav_entity_type'),
                'entity_type_id'
            )
            ->where('entity_type_code = \'catalog_category\'');

        return $this->dbConnection->fetchOne($select);
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

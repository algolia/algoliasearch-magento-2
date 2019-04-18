<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel;

use Algolia\AlgoliaSearch\Api\Data\QueryInterface;

class Query extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init(QueryInterface::TABLE_NAME, QueryInterface::FIELD_QUERY_ID);
    }

    /**
     * Check if the query_text already exists
     * return  id if page exists
     *
     * @param string $queryText
     * @param int $storeId
     * @param int $queryId
     *
     * @return int
     */
    public function checkQueryUnicity($queryText, $storeId = null, $queryId = null)
    {
        $select = $this->getConnection()
            ->select()
            ->from(['q' => $this->getMainTable()])
            ->where('query_text = ?', $queryText);

        // Only check a particular store if specified
        if (!is_null($storeId) && $storeId != 0) {
            $select->where('store_id = ?', $storeId);
        }

        // Handle the already existing query text for the query
        if (!is_null($queryId) && $queryId != 0) {
            $select->where('query_id != ?', $queryId);
        }

        $select->limit(1);

        return $this->getConnection()->fetchOne($select);
    }
}

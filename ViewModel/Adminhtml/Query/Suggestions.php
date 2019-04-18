<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml\Query;

use Algolia\AlgoliaSearch\Model\ResourceModel\Query\CollectionFactory as QueryCollectionFactory;

class Suggestions
{
    /** @var QueryCollectionFactory */
    private $queryCollectionFactory;

    /**
     * @param QueryCollectionFactory $queryCollectionFactory
     */
    public function __construct(QueryCollectionFactory $queryCollectionFactory)
    {
        $this->queryCollectionFactory = $queryCollectionFactory;
    }

    public function getNbOfQueries()
    {
        $queryCollection = $this->queryCollectionFactory->create();

        return $queryCollection->getSize();
    }
}

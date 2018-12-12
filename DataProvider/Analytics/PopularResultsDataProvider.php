<?php

namespace Algolia\AlgoliaSearch\DataProvider\Analytics;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollection;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollection;

class PopularResultsDataProvider
{
    /** @var ProductCollection */
    private $productCollection;

    /** @var CategoryCollection */
    private $categoryCollection;

    /** @var PageCollection */
    private $pageCollection;

    public function __construct(
        ProductCollection $productCollection,
        CategoryCollection $categoryCollection,
        PageCollection $pageCollection
    ) {
        $this->productCollection = $productCollection;
        $this->categoryCollection = $categoryCollection;
        $this->pageCollection = $pageCollection;
    }

    /**
     * @param int $storeId
     * @param array $objectIds
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getProductCollection($storeId, $objectIds)
    {
        $collection = $this->productCollection->create();

        $collection
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('entity_id', ['in' => $objectIds]);

        return $collection;
    }

    /**
     * @param int $storeId
     * @param array $objectIds
     *
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    public function getCategoryCollection($storeId, $objectIds)
    {
        $collection = $this->categoryCollection->create();

        $collection
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('entity_id', ['in' => $objectIds]);

        return $collection;
    }

    /**
     * @param int $storeId
     * @param array $objectIds
     *
     * @return \Magento\Cms\Model\ResourceModel\Page\Collection
     */
    public function getPageCollection($storeId, $objectIds)
    {
        $collection = $this->pageCollection->create();

        $collection
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addFieldToSelect(['page_id', 'title', 'identifier'])
            ->addFieldToFilter('page_id', ['in' => $objectIds]);

        return $collection;
    }
}

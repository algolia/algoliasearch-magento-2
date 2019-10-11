<?php

namespace Algolia\AlgoliaSearch\Model\Layer\Category;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class FilterableAttributeList extends \Magento\Catalog\Model\Layer\Category\FilterableAttributeList
{
    /** @var ConfigHelper */
    private $configHelper;

    /**
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        ConfigHelper $configHelper
    ) {
        parent::__construct($collectionFactory, $storeManager);

        $this->configHelper = $configHelper;
    }

    protected function _prepareAttributeCollection($collection)
    {
        $collection->addIsFilterableFilter();

        if ($this->configHelper->isBackendRenderingEnabled()) {
            $facets = $this->configHelper->getFacets($this->storeManager->getStore()->getId());
            $filterAttributes = [];
            foreach ($facets as $facet) {
                $filterAttributes[] = $facet['attribute'];
            }

            $collection->addFieldToFilter('attribute_code', ['in' => $filterAttributes]);
            $collection->setOrder('attribute_id', 'ASC');
        }

        return $collection;
    }
}

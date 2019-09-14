<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Store\Model\StoreManagerInterface;

class LayerPlugin
{
    /** @var ConfigHelper */
    private $configHelper;

    /** @var StoreManagerInterface */
    private $storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ConfigHelper $configHelper
    ) {
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * Adding relevance ordering on product collection to make sure it's compatible with replica index targeting
     *
     * @param Layer $subject
     * @param ProductCollection $result
     */
    public function afterGetProductCollection(Layer $subject, $result)
    {
        $storeId = $this->storeManager->getStore()->getId();

        if ($this->configHelper->isBackendRenderingEnabled($storeId)) {
            $result->setOrder('relevance');
        }

        return $result;
    }
}

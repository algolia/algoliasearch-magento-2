<?php

namespace Algolia\AlgoliaSearch\Plugin\Cache;

use Algolia\AlgoliaSearch\Helper\Entity\Product\CacheHelper;
use Magento\Catalog\Controller\Adminhtml\Product\Action\Attribute\Save;
use Magento\Catalog\Helper\Product\Edit\Action\Attribute;
use Magento\Framework\Controller\Result\Redirect;

class CacheCleanBulkAttributePlugin
{
    public function __construct(
        protected Attribute $attributeHelper,
        protected CacheHelper $cacheHelper
    ) {}

   /** In the event that the product_action_attribute.update consumer does not handle this change and update occurs in process
    *  then this plugin will preemptively clear the cache
    */
    public function afterExecute(
        Save $subject,
        Redirect $result
    ): Redirect
    {
        $this->cacheHelper->handleBulkAttributeChange(
            $this->attributeHelper->getProductIds(),
            $subject->getRequest()->getParam('attributes', []),
            $this->attributeHelper->getSelectedStoreId()
        );

        return $result;
    }
}

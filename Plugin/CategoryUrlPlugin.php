<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Magento\Catalog\Model\Category;
use Magento\Framework\ObjectManagerInterface;

/**
 * The purpose of this class is to fix an issue during indexing where frontend URLs were using
 * the default urls in a multistore environment even when emulating a store code, due to the URL di injection.
 * The categories would create urls that pointed to the admin backend, so this fixes so that the correct frontend
 * urls were created and indexed
 */
class CategoryUrlPlugin
{
    private $objectManager;

    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    public function aroundGetUrlInstance(Category $category, \Closure $proceed)
    {
        if ($category->getStoreId() === 0) {
            return $proceed();
        }

        return $this->objectManager->create(\Magento\Framework\Url::class)
            ->setStoreId($category->getStoreId());
    }
}

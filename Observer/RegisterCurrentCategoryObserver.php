<?php

namespace Algolia\AlgoliaSearch\Observer;

use Algolia\AlgoliaSearch\Registry\CurrentCategory;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RegisterCurrentCategoryObserver implements ObserverInterface
{
    /** @var CurrentCategory  */
    private CurrentCategory $currentCategory;

    public function __construct(CurrentCategory $currentCategory) {
        $this->currentCategory = $currentCategory;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        /** @var $category CategoryInterface */
        $category = $observer->getEvent()->getData('category');
        $this->currentCategory->set($category);
    }
}

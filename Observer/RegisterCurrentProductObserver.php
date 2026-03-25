<?php

namespace Algolia\AlgoliaSearch\Observer;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Event\Observer as Event;
use Magento\Framework\Event\ObserverInterface;
use Algolia\AlgoliaSearch\Registry\CurrentProduct;

/**
 * Class RegisterCurrentProductObserver
 *
 * Current product observer
 */
class RegisterCurrentProductObserver implements ObserverInterface
{
    /** @var CurrentProduct */
    private $currentProduct;

    /**
     * RegisterCurrentProductObserver constructor.
     *
     */
    public function __construct(
        CurrentProduct $currentProduct
    ) {
        $this->currentProduct = $currentProduct;
    }

    /**
     * Trigger event
     *
     */
    public function execute(Event $event)
    {
        /** @var ProductInterface $product */
        $product = $event->getData('product');
        $this->currentProduct->set($product);
    }
}

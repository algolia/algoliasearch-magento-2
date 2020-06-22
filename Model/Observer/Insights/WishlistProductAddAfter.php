<?php

namespace Algolia\AlgoliaSearch\Model\Observer\Insights;

use Algolia\AlgoliaSearch\Helper\Configuration\PersonalizationHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class WishlistProductAddAfter implements ObserverInterface
{
    /** @var Data */
    private $dataHelper;

    /** @var PersonalizationHelper */
    private $personalisationHelper;

    /** @var InsightsHelper */
    private $insightsHelper;

    /**
     * CheckoutCartProductAddAfter constructor.
     *
     * @param Data $dataHelper
     * @param PersonalizationHelper $personalisationHelper
     * @param InsightsHelper $insightsHelper
     */
    public function __construct(
        Data $dataHelper,
        PersonalizationHelper $personalisationHelper,
        InsightsHelper $insightsHelper
    ) {
        $this->dataHelper = $dataHelper;
        $this->personalisationHelper = $personalisationHelper;
        $this->insightsHelper = $insightsHelper;
    }

    /**
     * @param Observer $observer
     * ['order' => $this]
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $items = $observer->getEvent()->getItems();
        /** @var \Magento\Wishlist\Model\Item $firstItem */
        $firstItem = $items[0];

        if (!$this->personalisationHelper->isPersoEnabled($firstItem->getStoreId())
            || !$this->personalisationHelper->isWishlistAddTracked($firstItem->getStoreId())) {
            return;
        }

        $userClient = $this->insightsHelper->getUserInsightsClient();
        $productIds = [];

        /** @var \Magento\Wishlist\Model\Item $item */
        foreach ($items as $item) {
            $productIds[] = $item->getProductId();
        }

        $userClient->convertedObjectIDs(
            __('Added to Wishlist'),
            $this->dataHelper->getIndexName('_products', $firstItem->getStoreId()),
            $productIds
        );
    }
}

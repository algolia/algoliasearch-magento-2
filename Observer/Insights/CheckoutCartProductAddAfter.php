<?php

namespace Algolia\AlgoliaSearch\Observer\Insights;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\PersonalizationHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;
use Psr\Log\LoggerInterface;

class CheckoutCartProductAddAfter implements ObserverInterface
{
    protected ConfigHelper $configHelper;
    protected PersonalizationHelper $personalizationHelper;

    public function __construct(
        protected ProductHelper    $productHelper,
        protected InsightsHelper   $insightsHelper,
        protected RequestInterface $request,
        protected LoggerInterface  $logger
    )
    {
        $this->configHelper = $this->insightsHelper->getConfigHelper();
        $this->personalizationHelper = $this->insightsHelper->getPersonalizationHelper();
    }

    protected function addQueryIdToQuoteItems(Product $product, Item $quoteItem, string $queryId): void
    {
        if ($product->getTypeId() == "grouped") {
            $groupProducts = $product->getTypeInstance()->getAssociatedProducts($product);
            foreach ($quoteItem->getQuote()->getAllItems() as $item) {
                foreach ($groupProducts as $groupProduct) {
                    if ($groupProduct->getId() == $item->getProductId()) {
                        $item->setData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM, $queryId);
                    }
                }
            }
        } else {
            $quoteItem->setData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM, $queryId);
        }
    }

    /**
     * @param Observer $observer
     * ['quote_item' => $quoteItem, 'product' => $product]
     */
    public function execute(Observer $observer): void
    {
        /** @var Item $quoteItem */
        $quoteItem = $observer->getEvent()->getData('quote_item');
        /** @var Product $product */
        $product = $observer->getEvent()->getData('product');
        $storeId = $quoteItem->getStoreId();

        $isAddToCartTracked = $this->insightsHelper->isAddedToCartTracked($storeId);

        if (!$isAddToCartTracked
            && !$this->insightsHelper->isOrderPlacedTracked($storeId)
            || !$this->insightsHelper->getUserAllowedSavedCookie()) {
            return;
        }

        $eventProcessor = $this->insightsHelper->getEventProcessor();

        $queryId = $this->request->getParam('queryID');

        // Adding algolia_query_param to the items to track the conversion when product is added to the cart
        if ($this->insightsHelper->isConversionTrackedPlaceOrder($storeId) && $queryId) {
            $this->addQueryIdToQuoteItems($product, $quoteItem, $queryId);
        }

        // This logic handles both perso and conversion tracking
        if ($isAddToCartTracked) {
            try {
                $eventProcessor->convertAddToCart(
                    __('Added to Cart'),
                    $this->productHelper->getIndexName($storeId),
                    $quoteItem,
                    // A queryID should *only* be sent for conversions
                    // See https://www.algolia.com/doc/guides/sending-events/concepts/event-types/
                    $this->insightsHelper->isConversionTrackedAddToCart($storeId) ? $queryId : null
                );
            } catch (AlgoliaException $e) {
                $this->logger->critical("Unable to send add to cart event due to Algolia events model misconfiguration: " . $e->getMessage());
            } catch (LocalizedException $e) {
                $this->logger->error("Error tracking conversion for add to cart event: " . $e->getMessage());
            }
        }
    }
}

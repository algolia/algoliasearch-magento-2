<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Cart\RequestInfoFilterInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class AddToCartRedirectForInsights
{
    /**
     * @var RequestInfoFilterInterface
     */
    private $requestInfoFilter;

    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ProductRepositoryInterface $productRepository,
        protected Session $checkoutSession,
        protected StockRegistryInterface $stockRegistry,
        protected ManagerInterface $eventManager,
        protected ConfigHelper $configHelper,
    ) {}

    /**
     * @param Cart $cartModel
     * @param int|Product $productInfo
     * @param array|int|DataObject|null $requestInfo
     *
     * @return null
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function beforeAddProduct(Cart $cartModel, int|Product $productInfo, array|int|DataObject $requestInfo = null)
    {
        // First, check is Insights are enabled
        if (!$this->configHelper->isClickConversionAnalyticsEnabled($this->storeManager->getStore()->getId())) {
            return;
        }

        // If the request doesn't have any insights info, no need to handle it
        if (!isset($requestInfo['referer']) || !isset($requestInfo['queryID']) || !isset($requestInfo['indexName'])) {
            return;
        }

        // Check if the request comes from the PLP handled by InstantSearch
        if ($requestInfo['referer'] != 'instantsearch') {
            return;
        }

        $product = $this->getProduct($productInfo);
        $productId = $product->getId();

        if ($productId) {
            $request = $this->getQtyRequest($product, $requestInfo);

            try {
                $result = $product->getTypeInstance()->prepareForCartAdvanced($request, $product);
            } catch (LocalizedException $e) {
                $this->checkoutSession->setUseNotice(false);
                $result = $e->getMessage();
            }

            // if the result is a string, this mean that the product can't be added to the cart
            // see Magento\Quote\Model\Quote::addProduct()
            // Here we need to add the insights information to the redirect
            if (is_string($result)) {
                $redirectUrl = $product->getUrlModel()->getUrl(
                    $product,
                    [
                        '_query' => [
                            'objectID' => $product->getId(),
                            'queryID' => $requestInfo['queryID'],
                            'indexName' => $requestInfo['indexName']
                        ]
                    ]
                );

                $this->checkoutSession->setRedirectUrl($redirectUrl);
                if ($this->checkoutSession->getUseNotice() === null) {
                    $this->checkoutSession->setUseNotice(true);
                }
                throw new LocalizedException(__($result));
            }
        }
    }

    /**
     * @param $productInfo
     *
     * @return Product
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function getProduct($productInfo): Product
    {
        $product = null;
        if ($productInfo instanceof Product) {
            $product = $productInfo;
            if (!$product->getId()) {
                throw new LocalizedException(
                    __("The product wasn't found. Verify the product and try again.")
                );
            }
        } elseif (is_int($productInfo) || is_string($productInfo)) {
            $storeId = $this->storeManager->getStore()->getId();
            try {
                $product = $this->productRepository->getById($productInfo, false, $storeId);
            } catch (NoSuchEntityException $e) {
                throw new LocalizedException(
                    __("The product wasn't found. Verify the product and try again."),
                    $e
                );
            }
        } else {
            throw new LocalizedException(
                __("The product wasn't found. Verify the product and try again.")
            );
        }
        $currentWebsiteId = $this->storeManager->getStore()->getWebsiteId();
        if (!is_array($product->getWebsiteIds()) || !in_array($currentWebsiteId, $product->getWebsiteIds())) {
            throw new LocalizedException(
                __("The product wasn't found. Verify the product and try again.")
            );
        }
        return $product;
    }

    /**
     * Get request quantity
     *
     * @param Product $product
     * @param DataObject|int|array $request
     * @return int|DataObject
     */
    protected function getQtyRequest($product, $request = 0)
    {
        $request = $this->getProductRequest($request);
        $stockItem = $this->stockRegistry->getStockItem($product->getId(), $product->getStore()->getWebsiteId());
        $minimumQty = $stockItem->getMinSaleQty();
        //If product quantity is not specified in request and there is set minimal qty for it
        if ($minimumQty
            && $minimumQty > 0
            && !$request->getQty()
        ) {
            $request->setQty($minimumQty);
        }

        return $request;
    }

    /**
     * Get request for product add to cart procedure
     *
     * @param DataObject|int|array $requestInfo
     * @return DataObject
     * @throws LocalizedException
     */
    protected function getProductRequest($requestInfo)
    {
        if ($requestInfo instanceof DataObject) {
            $request = $requestInfo;
        } elseif (is_numeric($requestInfo)) {
            $request = new DataObject(['qty' => $requestInfo]);
        } elseif (is_array($requestInfo)) {
            $request = new DataObject($requestInfo);
        } else {
            throw new LocalizedException(
                __('We found an invalid request for adding product to quote.')
            );
        }
        $this->getRequestInfoFilter()->filter($request);

        return $request;
    }

    /**
     * Getter for RequestInfoFilter
     *
     * @return RequestInfoFilterInterface
     */
    protected function getRequestInfoFilter()
    {
        if ($this->requestInfoFilter === null) {
            $this->requestInfoFilter = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(RequestInfoFilterInterface::class);
        }
        return $this->requestInfoFilter;
    }
}

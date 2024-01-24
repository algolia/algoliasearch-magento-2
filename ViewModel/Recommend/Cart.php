<?php

namespace Algolia\AlgoliaSearch\ViewModel\Recommend;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

class Cart implements ArgumentInterface
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var ProductHelper
     */
    protected $productHelper;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param ConfigHelper $configHelper
     * @param ProductHelper $productHelper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        ConfigHelper $configHelper,
        ProductHelper $productHelper
    ) {
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->configHelper = $configHelper;
        $this->productHelper = $productHelper;
    }

    /**
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAllCartItems()
    {
        $cartItems = [];
        $visibleCartItem = [];
        $itemCollection = $this->checkoutSession->getQuote()->getAllVisibleItems();
        foreach ($itemCollection as $item) {
            $cartItems[] = $item->getProductId();
        }
        $storeId = $this->storeManager->getStore()->getId();
        $cartProductCollection = $this->productHelper->getProductCollectionQuery($storeId, array_unique($cartItems));
        if ($cartProductCollection->getSize() > 0 ){
            foreach ($cartProductCollection as $product) {
                $visibleCartItem[] = $product->getId();
            }
        }
        return $visibleCartItem;
    }

    /**
     * @return array
     */
    public function getAlgoliaRecommendConfiguration()
    {
        return [
            'enabledFBTInCart' => $this->configHelper->isRecommendFrequentlyBroughtTogetherEnabledOnCartPage(),
            'enabledRelatedInCart' => $this->configHelper->isRecommendRelatedProductsEnabledOnCartPage(),
            'isTrendItemsEnabledInCartPage' => $this->configHelper->isTrendItemsEnabledInShoppingCart()
        ];
    }
}

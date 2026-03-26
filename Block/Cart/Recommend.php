<?php

namespace Algolia\AlgoliaSearch\Block\Cart;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Recommend extends Template
{
    /** @var Session */
    protected $checkoutSession;

    /** @var ConfigHelper */
    protected $configHelper;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        ConfigHelper $configHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->configHelper = $configHelper;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     *
     * @return array
     */
    public function getAllCartItems()
    {
        $cartItems = [];
        $itemCollection = $this->checkoutSession->getQuote()->getAllVisibleItems();
        foreach ($itemCollection as $item) {
            $cartItems[] = $item->getProductId();
        }

        return array_unique($cartItems);
    }

    /**
     * @return array
     */
    public function getAlgoliaRecommendConfiguration()
    {
        return [
            'enabledFBTInCart' => $this->configHelper->isRecommendFrequentlyBroughtTogetherEnabledOnCartPage(),
            'enabledRelatedInCart' => $this->configHelper->isRecommendRelatedProductsEnabledOnCartPage(),
            'isTrendItemsEnabledInCartPage' => $this->configHelper->isTrendItemsEnabledInShoppingCart(),
             ];
    }
}
<?php

namespace Algolia\AlgoliaSearch\Block\Instant;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\PriceKeyResolver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;

class Hit extends Template
{
    public function __construct(
        protected ConfigHelper $config,
        protected PriceKeyResolver $priceKeyResolver,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getPriceKey(): string
    {
        $store = $this->_storeManager->getStore();
        return $this->priceKeyResolver->getPriceKey($store->getId());
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getCurrencyCode(): string
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->_storeManager->getStore();
        return $store->getCurrentCurrencyCode();
    }
}

<?php

namespace Algolia\AlgoliaSearch\Model\Layer\Filter;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class Price extends \Magento\CatalogSearch\Model\Layer\Filter\Price
{
    /** @var \Magento\Catalog\Model\Layer\Filter\DataProvider\Price */
    private $dataProvider;

    /** @var \Magento\Customer\Model\Session */
    private $customerSession;

    /** @var \Magento\Framework\Pricing\PriceCurrencyInterface */
    private $priceCurrency;

    /** @var ConfigHelper */
    private $configHelper;

    /**
     * @param \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer $layer
     * @param \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder
     * @param \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $resource
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Search\Dynamic\Algorithm $priceAlgorithm
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory $algorithmFactory
     * @param \Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory $dataProviderFactory
     * @param ConfigHelper $configHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $resource,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Search\Dynamic\Algorithm $priceAlgorithm,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory $algorithmFactory,
        \Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory $dataProviderFactory,
        ConfigHelper $configHelper,
        array $data = []
    ) {
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $resource,
            $customerSession,
            $priceAlgorithm,
            $priceCurrency,
            $algorithmFactory,
            $dataProviderFactory,
            $data
        );

        $this->dataProvider = $dataProviderFactory->create(['layer' => $this->getLayer()]);
        $this->configHelper = $configHelper;
        $this->priceCurrency = $priceCurrency;
    }

    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        $storeId = $this->configHelper->getStoreId();
        if (!$this->configHelper->isBackendRenderingEnabled($storeId)) {
            return parent::apply($request);
        }

        $filter = $request->getParam($this->getRequestVar());

        if ($filter && !is_array($filter)) {
            $this->dataProvider->setInterval($filter);

            list($fromValue, $toValue) = explode('-', $filter);
            $this->setCurrentValue(['from' => $fromValue, 'to' => $toValue]);

            $this->getLayer()->getState()->addFilter(
                $this->_createItem($this->_renderRangeLabel(empty($fromValue) ? 0 : $fromValue, $toValue), $filter)
            );
        }

        return $this;
    }

    protected function _getItemsData()
    {
        $storeId = $this->configHelper->getStoreId();
        if (!$this->configHelper->isBackendRenderingEnabled($storeId)) {
            return parent::_getItemsData();
        }

        $attribute = $this->getAttributeModel();
        $this->_requestVar = $attribute->getAttributeCode();

        /** @var \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $productCollection */
        $productCollection = $this->getLayer()->getProductCollection();
        $facets = $productCollection->getFacetedData($attribute->getAttributeCode());
        $this->setMinValue($productCollection->getMinPrice());
        $this->setMaxValue($productCollection->getMaxPrice());

        $data = [];
        if (count($facets) > 0) {
            foreach ($facets as $key => $aggregation) {
                $count = $aggregation['count'];
                if (mb_strpos($key, '_') === false) {
                    continue;
                }
                $data[] = $this->prepareData($key, $count, $data);
            }
        }

        return $data;
    }

    private function prepareData($key, $count)
    {
        list($from, $to) = explode('_', $key);
        if ($from == '*') {
            $from = $this->getFrom($to);
        }
        if ($to == '*') {
            $to = $this->getTo($to);
        }
        $label = $this->_renderRangeLabel($from, $to);
        $value = $from . '-' . $to . $this->dataProvider->getAdditionalRequestData();

        $data = [
            'label' => $label,
            'value' => $value,
            'count' => $count,
            'from' => $from,
            'to' => $to,
        ];

        return $data;
    }

    protected function _renderRangeLabel($fromPrice, $toPrice)
    {
        $fromPrice = empty($fromPrice) ? 0 : $fromPrice * $this->getCurrencyRate();
        $toPrice = empty($toPrice) ? $toPrice : $toPrice * $this->getCurrencyRate();

        $formattedFromPrice = $this->priceCurrency->format($fromPrice);
        if ($toPrice === '') {
            return __('%1 and above', $formattedFromPrice);
        } elseif ($fromPrice == $toPrice && $this->dataProvider->getOnePriceIntervalValue()) {
            return $formattedFromPrice;
        }

        return __('%1 - %2', $formattedFromPrice, $this->priceCurrency->format($toPrice));
    }
}

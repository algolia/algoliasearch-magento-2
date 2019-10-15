<?php

namespace Algolia\AlgoliaSearch\Model\Layer\Filter;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class Decimal extends \Magento\CatalogSearch\Model\Layer\Filter\Decimal
{
    /** @var \Magento\Catalog\Model\Layer\Filter\DataProvider\Price */
    private $dataProvider;

    /** @var \Magento\Framework\Locale\ResolverInterface $localeResolver */
    private $localeResolver;

    /** @var ConfigHelper */
    private $configHelper;

    /**
     * @param \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer $layer
     * @param \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder
     * @param \Magento\Catalog\Model\ResourceModel\Layer\Filter\DecimalFactory $filterDecimalFactory
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory $dataProviderFactory
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param ConfigHelper $configHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\DecimalFactory $filterDecimalFactory,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory $dataProviderFactory,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        ConfigHelper $configHelper,
        array $data
    ) {
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $filterDecimalFactory,
            $priceCurrency,
            $data
        );
        $this->localeResolver = $localeResolver;
        $this->configHelper = $configHelper;
        $this->dataProvider = $dataProviderFactory->create(['layer' => $this->getLayer()]);
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
                $data[] = $this->prepareData($key, $count);
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

    protected function _renderRangeLabel($fromValue, $toValue)
    {
        $label = $this->formatValue($fromValue);

        if ($toValue === '') {
            $label = __('%1 and above', $label);
        } elseif ($fromValue != $toValue) {
            $label = __('%1 - %2', $label, $this->formatValue($toValue));
        }

        return $label;
    }

    private function formatValue($value)
    {
        $attribute = $this->getAttributeModel();

        if ((int) $attribute->getDisplayPrecision() > 0) {
            $locale = $this->localeResolver->getLocale();
            $options = ['locale' => $locale, 'precision' => (int) $attribute->getDisplayPrecision()];
            $valueFormatter = new \Zend_Filter_NormalizedToLocalized($options);
            $value = $valueFormatter->filter($value);
        }

        if ((string) $attribute->getDisplayPattern() != '') {
            $value = sprintf((string) $attribute->getDisplayPattern(), $value);
        }

        return $value;
    }
}

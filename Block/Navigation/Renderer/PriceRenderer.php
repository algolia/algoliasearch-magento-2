<?php

namespace Algolia\AlgoliaSearch\Block\Navigation\Renderer;

use Magento\Catalog\Model\Layer\Filter\DataProvider\Price as PriceDataProvider;
use Magento\Store\Model\ScopeInterface;

class PriceRenderer extends SliderRenderer
{
    /** @var string */
    protected $dataRole = 'range-price-slider';

    /**
     * @return array
     */
    protected function getFieldFormat()
    {
        return $this->localeFormat->getPriceFormat();
    }

    /**
     * {@inheritdoc}
     */
    protected function getConfig()
    {
        $config = parent::getConfig();

        if ($this->isManualCalculation() && ($this->getStepValue() > 0)) {
            $config['step'] = $this->getStepValue();
        }

        if ($this->getFilter()->getCurrencyRate()) {
            $config['rate'] = $this->getFilter()->getCurrencyRate();
        }

        return $config;
    }

    /** @return int */
    protected function getMinValue()
    {
        $minValue = $this->getFilter()->getMinValue();

        if ($this->isManualCalculation() && ($this->getStepValue() > 0)) {
            $stepValue = $this->getStepValue();
            $minValue  = floor($minValue / $stepValue) * $stepValue;
        }

        return $minValue;
    }

    /* @return int */
    protected function getMaxValue()
    {
        $maxValue = $this->getFilter()->getMaxValue();

        if ($this->isManualCalculation() && ($this->getStepValue() > 0)) {
            $stepValue = $this->getStepValue();
            $maxValue  = ceil($maxValue / $stepValue) * $stepValue;
        }

        return $maxValue;
    }

    /* @return bool */
    private function isManualCalculation()
    {
        $calculation = $this->_scopeConfig->getValue(PriceDataProvider::XML_PATH_RANGE_CALCULATION, ScopeInterface::SCOPE_STORE);
        if ($calculation === PriceDataProvider::RANGE_CALCULATION_MANUAL) {
            return true;
        }

        return false;
    }

    /* @return int */
    private function getStepValue()
    {
        $value = $this->_scopeConfig->getValue(PriceDataProvider::XML_PATH_RANGE_STEP, ScopeInterface::SCOPE_STORE);

        return (int) $value;
    }
}

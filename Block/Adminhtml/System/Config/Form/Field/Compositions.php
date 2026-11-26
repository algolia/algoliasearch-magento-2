<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Compositions extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $optionValues = [];
        $currentValue = $element->getValue();

        foreach($element->getValues() as $value) {
            $optionValues[] = $value['value'];
        }

        if (empty($optionValues)) {
            return parent::_getElementHtml($element) . $this->getNoAvailableCompositionsNotice();
        }

        if ($currentValue === '' || $currentValue === null || in_array($currentValue, $optionValues)) {
            return parent::_getElementHtml($element);
        }

        return parent::_getElementHtml($element) . $this->getUnavailableCompositionNotice($currentValue);
    }

    protected function getUnavailableCompositionNotice(string $currentValue): string
    {
        $html = "<p class='note'><span><strong class='colorRed'>WARNING</strong>: ";
        $html .= __(
            'The composition configured (<strong>%1</strong>)
        does not seem to be available for this store, please check your compositions in your Algolia Merchandising Studio.',
            $currentValue
        );
        $html .="</p>";

        return $html;
    }

    protected function getNoAvailableCompositionsNotice(): string
    {
        $html = "<p class='note'><span><strong class='colorRed'>WARNING</strong>: ";
        $html .= __(
            'There are no compositions available to select
            , please check your compositions in your Algolia Merchandising Studio.'
        );
        $html .="</p>";

        return $html;
    }
}

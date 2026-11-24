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

        if ($currentValue === '' || $currentValue === null || in_array($currentValue, $optionValues)) {
            return parent::_getElementHtml($element);
        }

        $html = "<p class='note'><span><strong class='colorRed'>WARNING</strong>: ";
        $html .= __(
            'The composition previously configured (<strong>%1</strong>),
        does not seem to be available anymore, please check your compositions in your Algolia Merchandising Studio.',
             $currentValue
        );
        $html .="</p>";

        return parent::_getElementHtml($element) . $html;
    }
}

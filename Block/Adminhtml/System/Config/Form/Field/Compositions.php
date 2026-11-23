<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Compositions extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $optionValues = [];
        $currentValue = $element->getEscapedValue();

        foreach($element->getValues() as $value) {
            $optionValues[] = $value['value'];
        }

        if (in_array($element->getEscapedValue(), $optionValues)) {
            return parent::_getElementHtml($element);
        }

        $html = "<p class='note' style='color:red;'>
<span><strong>WARNING</strong>: The composition previously configured ($currentValue),
does not seem to be available anymore, please check your compositions in your Algolia Merchandising Studio.
</span>
</p>";

        return parent::_getElementHtml($element) . $html;
    }
}

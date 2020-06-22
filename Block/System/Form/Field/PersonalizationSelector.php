<?php

namespace Algolia\AlgoliaSearch\Block\System\Form\Field;

class PersonalizationSelector extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * Retrieve label for the inheritance checkbox
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    protected function _getInheritCheckboxLabel(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $checkboxLabel = __('Use default Magento selector');
        if ($element->getCanUseDefaultValue()) {
            $checkboxLabel = __('Use Default');
        }
        if ($element->getCanUseWebsiteValue()) {
            $checkboxLabel = __('Use Website');
        }

        return $checkboxLabel;
    }
}

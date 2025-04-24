<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Checkboxes extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $html = '';
        $name = $element->getName();
        $options = $element->getValues();
        $values = empty($element->getValue()) ? [] : explode(',', $element->getValue()); // store as CSV in config

        foreach ($options as $option) {
            $value = $option['value'];
            $label = $option['label'];
            $checked = in_array($value, $values) ? 'checked' : '';
            $html .= '<label style="display:block">';
            $html .= sprintf(
                '<input type="checkbox" name="%s[]" value="%s" %s /> %s',
                $name,
                $value,
                $checked,
                $label
            );
            $html .= '</label>';
        }

        return $html;
    }
}

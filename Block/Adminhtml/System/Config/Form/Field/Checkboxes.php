<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Checkboxes extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $elementId = $element->getHtmlId();
        $html = <<<CSS
            <style>
                #$elementId .form-group {
                  display: grid;
                  grid-template-columns: 24px 1fr;
                  align-items: start;
                  margin-bottom: 20px;
                }

                #$elementId .form-group input[type="checkbox"] {
                  margin-top: 2px;
                }
                #$elementId .form-content {
                  display: flex;
                  flex-direction: column;
                }
                #$elementId .form-content label {
                  font-weight: bold;
                  color: #333;
                }
                #$elementId .form-content .description {
                  font-size: 0.9em;
                  color: #666;
                  margin-top: 2px;
                }
            </style>
        CSS;

        $html .= sprintf('<div id="%s">', $elementId);
        $name = $element->getName();
        $options = $element->getValues();
        $values = empty($element->getValue()) ? [] : explode(',', $element->getValue()); // store as CSV in config

        foreach ($options as $option) {
            $value = $option['value'];
            $html .= $this->getCheckboxHtml(
                $name,
                $value,
                in_array($value, $values),
                $option['label'],
                array_key_exists('description', $option) ? $option['description'] : ''
            );
        }

        $html .= '</div>';
        return $html;
    }

    protected function getCheckboxHtml(
        string $name,
        string $value,
        bool $checked,
        ?string $label = null,
        ?string $description = null
    ): string
    {
        $html = '<div class="form-group">';
        $html .= sprintf(
            '<input type="checkbox" name="%s[]" value="%s" %s />',
            $name,
            $value,
            $checked ? 'checked' : ''
        );
        $html .= '<div class="form-content">';
        $html .= sprintf('<label>%s</label>',$label ?? $name);
        if ($description) {
            $html .= sprintf('<span class="description">%s</span>', $description);
        }
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
}

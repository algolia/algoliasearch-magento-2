<?php

namespace Algolia\AlgoliaSearch\Block\System\Form\Field;

class Select extends \Magento\Framework\View\Element\Html\Select
{
    protected function _toHtml()
    {
        $this->setData('name', $this->getData('input_name'));
        $this->setClass('select');

        return trim(preg_replace('/\s+/', ' ', parent::_toHtml()));
    }
}

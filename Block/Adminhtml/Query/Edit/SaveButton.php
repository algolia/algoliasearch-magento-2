<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Query\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveButton extends AbstractButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        return [
            'label' => __('Save query'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}

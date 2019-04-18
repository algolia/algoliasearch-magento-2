<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Query\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveAndContinueButton extends AbstractButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        return [
            'label' => __('Save and continue edit'),
            'class' => 'save',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'saveAndContinueEdit']],
                'form-role' => 'save',
            ],
            'sort_order' => 80,
        ];
    }
}

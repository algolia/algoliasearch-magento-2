<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\LandingPage\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveButton extends AbstractButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        return [
            'label' => __('Save landing page'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}

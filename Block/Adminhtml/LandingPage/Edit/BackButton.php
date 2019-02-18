<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\LandingPage\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class BackButton extends AbstractButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        return [
            'label'  => __('Back'),
            'on_click' => sprintf("location.href = '%s';", $this->getBackUrl()),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }

    public function getBackUrl()
    {
        return $this->getUrl('*/*/');
    }
}

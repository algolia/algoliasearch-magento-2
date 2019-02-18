<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\LandingPage\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DuplicateButton extends AbstractButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        $data = [];
        if ($this->getObjectId()) {
            $message = htmlentities(__('Are you sure you want to duplicate this landing page?'));

            $data = [
                'label'      => __('Duplicate'),
                'class'      => 'duplicate',
                'on_click'   => "deleteConfirm('{$message}', '{$this->getDuplicateUrl()}')",
                'sort_order' => 30,
            ];
        }

        return $data;
    }

    public function getDuplicateUrl()
    {
        return $this->getUrl('*/*/duplicate', ['id' => $this->getObjectId()]);
    }
}

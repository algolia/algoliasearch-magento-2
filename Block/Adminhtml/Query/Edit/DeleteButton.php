<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Query\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends AbstractButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        $data = [];
        if ($this->getObjectId()) {
            $message = htmlentities(__('Are you sure you want to delete this query?'));

            $data = [
                'label'      => __('Delete'),
                'class'      => 'delete',
                'on_click'   => "deleteConfirm('{$message}', '{$this->getDeleteUrl()}')",
                'sort_order' => 20,
            ];
        }

        return $data;
    }

    public function getDeleteUrl()
    {
        return $this->getUrl('*/*/delete', ['id' => $this->getObjectId()]);
    }
}

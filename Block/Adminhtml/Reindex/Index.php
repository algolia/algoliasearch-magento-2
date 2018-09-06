<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Reindex;

use Magento\Backend\Block\Widget\Form\Container;

class Index extends Container
{
    protected function _construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'Algolia_AlgoliaSearch';
        $this->_controller = 'adminhtml_reindex';

        parent::_construct();
        $this->buttonList->update('save', 'label', __('Reindex SKU(s)'));
    }
}

<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Reindex\Edit;

use \Magento\Backend\Block\Widget\Form\Generic;

class Form extends Generic
{
    /**
     * Init form
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('reindex_skus');
        $this->setTitle(__('Reindex'));
    }

    /**
     * Prepare form
     *
     * @return $this
     */
    protected function _prepareForm()
    {

        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'edit_form',
                'action' => $this->getData('action'),
                'method' => 'post'
            ]
        ]);

        $fieldset = $form->addFieldset(
            'base_fieldset',
            [
                'legend' => __('Reindex SKU(s) separated by commas or carriage returns'),
                'class' => 'fieldset-wide'
            ]
        );

        $fieldset->addField(
            'skus',
            'textarea',
            [
                'name' => 'skus',
                'label' => __('SKU(s)'),
                'title' => __('SKU(s)'),
                'required' => true
            ]
        );

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}

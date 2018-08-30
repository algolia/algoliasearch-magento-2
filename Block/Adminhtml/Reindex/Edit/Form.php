<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Reindex\Edit;

use Magento\Backend\Block\Widget\Form\Generic;

class Form extends Generic
{
    protected function _construct()
    {
        parent::_construct();
        $this->setData('id', 'reindex_skus');
        $this->setData('title', __('Reindex'));
    }

    protected function _prepareForm()
    {

        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'edit_form',
                'action' => $this->getData('action'),
                'method' => 'post',
            ],
        ]);

        $fieldset = $form->addFieldset(
            'base_fieldset',
            [
                'legend' => __('Reindex SKU(s)'),
                'class' => 'fieldset-wide',
            ]
        );

        $html = '</br></br>';
        $html .= '<p>' . __('Enter here the SKU(s) you want to reindex separated by commas or carriage returns.') . '</p>';
        $html .= '<p>' . __('You will be notified if there is any reason why your product can\'t be reindexed.') . '</p>';
        $html .= '<p>' . __('It can be :') . '</p>';
        $html .= '<ul>';
        $html .= '<li>' . __('Product is disabled.') . '</li>';
        $html .= '<li>' . __('Product is deleted.') . '</li>';
        $html .= '<li>' . __('Product is out of stock.') . '</li>';
        $html .= '<li>' . __('Product is not visible.') . '</li>';
        $html .= '<li>' . __('Product is not related to the store.') . '</li>';
        $html .= '</ul>';
        $html .= '<p>' . __('You can reindex up to 10 SKUs at once.') . '</p>';

        $fieldset->addField(
            'skus',
            'textarea',
            [
                'name' => 'skus',
                'label' => __('SKU(s)'),
                'title' => __('SKU(s)'),
                'after_element_html' => $html,
                'required' => true,
            ]
        );

        $form->setData('use_container', true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}

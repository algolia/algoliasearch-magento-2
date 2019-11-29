<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\System\Config;

class Save extends \Magento\Config\Controller\Adminhtml\System\Config\Save
{
    private $instantSerializedValues = ['facets', 'sorts'];
    private $autocompleteSerializedValues = ['sections', 'excluded_pages'];

    protected function _getGroupsForSave()
    {
        $groups = parent::_getGroupsForSave();

        return $this->handleDeactivatedSerializedArrays($groups);
    }

    private function handleDeactivatedSerializedArrays($groups)
    {
        if (isset($groups['autocomplete']['fields']['is_popup_enabled']['value'])
                && $groups['autocomplete']['fields']['is_popup_enabled']['value'] == '0') {
            foreach ($this->autocompleteSerializedValues as $field) {
                if (isset($groups['autocomplete']['fields'][$field])) {
                    unset($groups['autocomplete']['fields'][$field]);
                }
            }
        }

        if (isset($groups['instant']['fields']['is_instant_enabled']['value'])
            && $groups['instant']['fields']['is_instant_enabled']['value'] == '1') {
            $groups['instant']['fields']['backend_rendering_enable']['value'] = '0';
        }

        return $groups;
    }
}

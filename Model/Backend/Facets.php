<?php

namespace Algolia\AlgoliaSearch\Model\Backend;

use Magento\Config\Model\Config\Backend\Serialized;

/**
 * Algolia custom facet backend model
 */
class Facets extends Serialized
{
    public function beforeSave()
    {
        $values = $this->getValue();
        if (is_array($values)) {
            unset($values['__empty']);
        }

        $this->setValue($values);

        return parent::beforeSave();
    }
}

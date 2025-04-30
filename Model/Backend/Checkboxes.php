<?php

namespace Algolia\AlgoliaSearch\Model\Backend;

class Checkboxes extends \Magento\Framework\App\Config\Value
{
    public function beforeSave()
    {
        $value = $this->getValue();
        if (is_array($value)) {
            $this->setValue(implode(',', $value));
        }
        return parent::beforeSave();
    }
}

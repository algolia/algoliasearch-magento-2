<?php

namespace Algolia\AlgoliaSearch\Model\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Algolia custom sort order field
 */
class ExtraSettings extends Value
{
    public function beforeSave()
    {
        $value = trim($this->getValue());

        if (empty($value)) {
            return parent::beforeSave();
        }

        $label = (string) $this->getData('field_config/label');

        json_decode($value);
        $error = json_last_error();

        if ($error) {
            throw new LocalizedException(
                __('JSON provided for "%1" field is not valid JSON.', $label)
            );
        }

        return parent::beforeSave();
    }
}

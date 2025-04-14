<?php

namespace Algolia\AlgoliaSearch\Model\Backend;

use Algolia\AlgoliaSearch\Exception\InvalidCronException;
use Magento\Framework\App\Config\Value;

class QueueCron extends Value
{
    const CRON_REGEX = '/^(\*|[0-9,\-\/\*]+)\s+(\*|[0-9,\-\/\*]+)\s+(\*|[0-9,\-\/\*]+)\s+(\*|[0-9,\-\/\*]+)\s+(\*|[0-9,\-\/\*]+)$/';

    protected array $mappings = [
        '@yearly' => '0 0 1 1 *',
        '@annually' => '0 0 1 1 *',
        '@monthly' => '0 0 1 * *',
        '@weekly' => '0 0 * * 0',
        '@daily' => '0 0 * * *',
        '@hourly' => '0 * * * *'
    ];

    public function beforeSave()
    {
        $value = trim($this->getData('value'));

        if (isset($this->mappings[$value])) {
            $value = $this->mappings[$value];
            $this->setValue($value);
        }

        if (!preg_match(self::CRON_REGEX, $value)) {
            throw new InvalidCronException("Cron expression \"$value\" is not valid.");
        }

        return parent::beforeSave();
    }
}

<?php

namespace Algolia\AlgoliaSearch\Model\Backend;

use Algolia\AlgoliaSearch\Exception\InvalidCronException;
use Magento\Framework\App\Config\Value;

class QueueCron extends Value
{
    const CRON_FORMAT_REGEX = '/^(\*|[0-9,\-\/\*]+)\s+(\*|[0-9,\-\/\*]+)\s+(\*|[0-9,\-\/\*]+)\s+(\*|[0-9,\-\/\*]+)\s+(\*|[0-9,\-\/\*]+)$/';
    const CRON_DISALLOW_REGEX = '/[^@a-z0-9\*\-,\/ ]/';


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
        $value = trim((string) $this->getData('value'));

        if (isset($this->mappings[$value])) {
            $value = $this->mappings[$value];
            $this->setValue($value);
        }

        if (!preg_match(self::CRON_FORMAT_REGEX, $value)) {
            $safeValue = preg_replace(self::CRON_DISALLOW_REGEX, '', (string) $value);
            $msg = ($safeValue !== $value)
                ? 'Cron expression is invalid.'
                : sprintf(
                    'Cron expression "%s" is not valid.',
                    $safeValue
                );
            throw new InvalidCronException($msg);
        }

        return parent::beforeSave();
    }
}

<?php

namespace Algolia\AlgoliaSearch\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class AlgoliaLoggerHandler extends Base
{
    protected $fileName = '/var/log/algolia.log';
    protected $loggerType = Logger::DEBUG; // Default
}

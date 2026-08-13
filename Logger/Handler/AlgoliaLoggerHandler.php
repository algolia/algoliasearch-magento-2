<?php

namespace Algolia\AlgoliaSearch\Logger\Handler;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class AlgoliaLoggerHandler extends Base
{
    protected const FILE_NAME = '/var/log/algolia.log';

    public function __construct(
        DriverInterface $filesystem,
        ?string $filePath = null,
        ?string $fileName = self::FILE_NAME,
        int $loggerType = Logger::INFO // DEBUG = 100, INFO = 200, WARNING = 300
    ) {
        $this->loggerType = $loggerType;
        parent::__construct($filesystem, $filePath, $fileName);
    }
}

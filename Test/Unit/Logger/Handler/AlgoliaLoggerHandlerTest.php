<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Logger\Handler;

use Algolia\AlgoliaSearch\Logger\Handler\AlgoliaLoggerHandler;
use Magento\Framework\Filesystem\DriverInterface;

class AlgoliaLoggerHandlerTest extends AbstractHandlerTest
{
    protected function setUp(): void
    {
        $this->handler = new AlgoliaLoggerHandler(
            $this->createMock(DriverInterface::class)
        );
    }

    public function testAlgoliaHandlerLogsEverything(): void
    {
        $debugRecord = $this->makeLogRecord(
            \Monolog\Logger::DEBUG,
            'Should log'
        );

        $infoRecord = $this->makeLogRecord(
            \Monolog\Logger::INFO,
            'Should log'
        );

        $errorRecord = $this->makeLogRecord(
            \Monolog\Logger::ERROR,
            'Should log'
        );

        $this->assertTrue($this->handler->isHandling($debugRecord), 'DEBUG should be handled');
        $this->assertTrue($this->handler->isHandling($infoRecord), 'INFO should be handled');
        $this->assertTrue($this->handler->isHandling($errorRecord), 'ERROR should be handled');
    }
}

<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Logger\Handler;

use Algolia\AlgoliaSearch\Logger\Handler\AlgoliaLoggerHandler;
use Magento\Framework\Filesystem\DriverInterface;

class AlgoliaLoggerHandlerTest extends AbstractHandlerTestCase
{
    protected function createObjectToTest(?DriverInterface $driver = null): AlgoliaLoggerHandler
    {
        return new AlgoliaLoggerHandler($driver ?? $this->createStub(DriverInterface::class));
    }

    public function testAlgoliaHandlerLogsEverything(): void
    {
        $handler = $this->createObjectToTest();

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

        $this->assertFalse($handler->isHandling($debugRecord), 'DEBUG should not be handled unless overridden by DI');
        $this->assertTrue($handler->isHandling($infoRecord), 'INFO should be handled');
        $this->assertTrue($handler->isHandling($errorRecord), 'ERROR should be handled');
    }
}

<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Logger\Handler;

use Algolia\AlgoliaSearch\Logger\Handler\SystemLoggerHandler;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Exception as ExceptionHandler;

class SystemLoggerHandlerTest extends AbstractHandlerTestCase
{
    protected function createObjectToTest(
        ?DriverInterface $driver = null,
        ?ExceptionHandler $exceptionHandler = null,
    ): SystemLoggerHandler {
        return new SystemLoggerHandler(
            $driver ?? $this->createStub(DriverInterface::class),
            $exceptionHandler ?? $this->createStub(ExceptionHandler::class),
        );
    }

    public function testSystemHandlerFiltersBelowError(): void
    {
        $handler = $this->createObjectToTest();

        $infoRecord = $this->makeLogRecord(
            \Monolog\Logger::INFO,
            'Should not log'
        );

        $errorRecord = $this->makeLogRecord(
            \Monolog\Logger::ERROR,
            'Should log'
        );

        $this->assertFalse($handler->isHandling($infoRecord), 'INFO should be ignored');
        $this->assertTrue($handler->isHandling($errorRecord), 'ERROR should be handled');
    }
}

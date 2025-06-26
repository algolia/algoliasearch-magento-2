<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Logger\Handler;

use Algolia\AlgoliaSearch\Logger\Handler\SystemLoggerHandler;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Exception as ExceptionHandler;

class SystemLoggerHandlerTest extends AbstractHandlerTest
{
    /**
     * @throws \Exception
     */
    protected function setUp(): void
    {
        $this->handler = new SystemLoggerHandler(
            $this->createMock(DriverInterface::class),
            $this->createMock(ExceptionHandler::class),
        );
    }

    public function testSystemHandlerFiltersBelowError(): void
    {
        $infoRecord = $this->makeLogRecord(
            \Monolog\Logger::INFO,
            'Should not log'
        );

        $errorRecord = $this->makeLogRecord(
             \Monolog\Logger::ERROR,
            'Should log'
        );

        $this->assertFalse($this->handler->isHandling($infoRecord), 'INFO should be ignored');
        $this->assertTrue($this->handler->isHandling($errorRecord), 'ERROR should be handled');
    }
}

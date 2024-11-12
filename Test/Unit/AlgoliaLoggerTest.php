<?php

namespace Algolia\AlgoliaSearch\Test\Unit;

use Algolia\AlgoliaSearch\Logger\AlgoliaLogger;
use Algolia\AlgoliaSearch\Logger\AlgoliaLoggerHandler;
use Algolia\AlgoliaSearch\Logger\SystemLoggerHandler;
use PHPUnit\Framework\TestCase;

class AlgoliaLoggerTest extends TestCase
{
    protected AlgoliaLogger $algoliaLogger;
    protected SystemLoggerHandler $systemLoggerHandler;
    protected AlgoliaLoggerHandler $algoliaLoggerHandler;

    protected function setUp(): void
    {
        $this->systemLoggerHandler = $this->createMock(SystemLoggerHandler::class);
        $this->algoliaLoggerHandler = $this->createMock(AlgoliaLoggerHandler::class);

        $this->algoliaLogger = new AlgoliaLogger(
            'algolia',
            [ $this->systemLoggerHandler, $this->algoliaLoggerHandler ]
        );
    }

    public function testLog(): void
    {
        $expectedLogLevel = \Monolog\Logger::INFO;
        $this->systemLoggerHandler
            ->expects($this->once())
            ->method('isHandling')
            ->with(['level' => $expectedLogLevel])
            ->willReturn(false);
        $this->systemLoggerHandler->expects($this->never())->method('handle');
        $this->algoliaLoggerHandler
            ->expects($this->once())
            ->method('isHandling')
            ->with(['level' => $expectedLogLevel])
            ->willReturn(true);
        $this->algoliaLoggerHandler->expects($this->once())->method('handle');
        $this->algoliaLogger->info("Test log message");
    }

    public function testError(): void
    {
        $expectedLogLevel = \Monolog\Logger::ERROR;
        $this->systemLoggerHandler
            ->expects($this->once())
            ->method('isHandling')
            ->with(['level' => $expectedLogLevel])
            ->willReturn(true);
        $this->systemLoggerHandler->expects($this->once())->method('handle');
        $this->algoliaLoggerHandler
            ->expects($this->never())
            ->method('isHandling');
        $this->algoliaLoggerHandler->expects($this->once())->method('handle');
        $this->algoliaLogger->error("Test error message");
    }
}

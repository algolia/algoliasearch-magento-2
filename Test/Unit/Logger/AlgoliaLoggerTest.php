<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Logger;

use Algolia\AlgoliaSearch\Logger\Handler\AlgoliaLoggerHandler;
use Algolia\AlgoliaSearch\Logger\Handler\SystemLoggerHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AlgoliaLoggerTest extends TestCase
{
    protected LoggerInterface $algoliaLogger;
    protected SystemLoggerHandler $systemLoggerHandler;
    protected AlgoliaLoggerHandler $algoliaLoggerHandler;

    protected function setUp(): void
    {
        $this->systemLoggerHandler = $this->createMock(SystemLoggerHandler::class);
        $this->algoliaLoggerHandler = $this->createMock(AlgoliaLoggerHandler::class);

        $this->algoliaLogger = new Logger(
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

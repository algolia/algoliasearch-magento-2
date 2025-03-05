<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Logger;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Logger\TimedLogger;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use PHPUnit\Framework\TestCase;

class DiagnosticLoggerTest extends TestCase
{
    protected ConfigHelper $configHelper;
    protected TimedLogger $timedLogger;
    protected StoreNameFetcher $storeNameFetcher;
    protected DiagnosticsLogger $diagnosticsLogger;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->configHelper->method('isLoggingEnabled')->willReturn(true);

        $this->timedLogger = $this->createMock(TimedLogger::class);
        $this->storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $this->diagnosticsLogger = new DiagnosticsLogger(
            $this->configHelper,
            $this->timedLogger,
            $this->storeNameFetcher
        );
    }

    public function testLog(): void {
        $msg = "Adding a log message";
        $this->timedLogger->expects($this->once())
            ->method('log')
            ->with($msg, \Monolog\Logger::INFO);
        $this->diagnosticsLogger->log($msg);
    }

}

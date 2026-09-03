<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Logger;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Logger\TimedLogger;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use PHPUnit\Framework\TestCase;

class DiagnosticLoggerTest extends TestCase
{
    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?TimedLogger $timedLogger = null,
        ?StoreNameFetcher $storeNameFetcher = null,
    ): DiagnosticsLogger {
        $configHelper ??= $this->createStub(ConfigHelper::class);
        $configHelper->method('isLoggingEnabled')->willReturn(true);

        return new DiagnosticsLogger(
            $configHelper,
            $timedLogger ?? $this->createStub(TimedLogger::class),
            $storeNameFetcher ?? $this->createStub(StoreNameFetcher::class),
        );
    }

    public function testLog(): void
    {
        $msg = 'Adding a log message';

        $timedLogger = $this->createMock(TimedLogger::class);
        $timedLogger->expects($this->once())->method('log')->with($msg, \Monolog\Logger::INFO);

        $diagnosticsLogger = $this->createObjectToTest(timedLogger: $timedLogger);

        $diagnosticsLogger->log($msg);
    }
}

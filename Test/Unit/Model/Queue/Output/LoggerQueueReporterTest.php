<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue\Output;

use Algolia\AlgoliaSearch\Api\LoggerInterface;
use Algolia\AlgoliaSearch\Model\Queue\Output\LoggerQueueReporter;
use Algolia\AlgoliaSearch\Test\TestCase;

class LoggerQueueReporterTest extends TestCase
{
    public function testInfoIsLoggedAtInfoLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('all good');

        (new LoggerQueueReporter($logger))->info('all good');
    }

    public function testWarningIsLoggedAtWarningLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('heads up');

        (new LoggerQueueReporter($logger))->warning('heads up');
    }

    public function testErrorIsLoggedAtErrorLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('something broke');

        (new LoggerQueueReporter($logger))->error('something broke');
    }

    public function testLogIsLoggedAtInfoLevelWithoutAnyDecoration(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plain line');

        (new LoggerQueueReporter($logger))->log('plain line');
    }
}

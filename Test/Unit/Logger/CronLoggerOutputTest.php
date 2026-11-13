<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Logger;

use Algolia\AlgoliaSearch\Api\LoggerInterface;
use Algolia\AlgoliaSearch\Logger\CronLoggerOutput;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

class CronLoggerOutputTest extends TestCase
{
    public function testWritelnForwardsStrippedMessageAtInfoLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Processing the indexing queue in parallel for 2 store(s).');
        $logger->expects($this->never())->method('error');

        $output = new CronLoggerOutput($logger);
        $output->writeln('<info>Processing the indexing queue in parallel for 2 store(s).</info>');
    }

    public function testWriteForwardsStrippedMessageAtInfoLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('No pending indexing queue jobs.');
        $logger->expects($this->never())->method('error');

        $output = new CronLoggerOutput($logger);
        $output->write('<info>No pending indexing queue jobs.</info>');
    }

    public function testErrorTagIsForwardedAtErrorLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Store 2 (Second Store): failed (exit code 1)');
        $logger->expects($this->never())->method('info');

        $output = new CronLoggerOutput($logger);
        $output->writeln('<error>Store 2 (Second Store): failed (exit code 1)</error>');
    }

    public function testIterableMessagesAreLoggedOneEntryEach(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('first');
        $logger->expects($this->once())->method('error')->with('second');

        $output = new CronLoggerOutput($logger);
        $output->writeln(['<info>first</info>', '<error>second</error>']);
    }

    public function testVerbosityAndDecorationAreFixed(): void
    {
        $output = new CronLoggerOutput($this->createStub(LoggerInterface::class));

        $this->assertSame(OutputInterface::VERBOSITY_NORMAL, $output->getVerbosity());
        $this->assertFalse($output->isDecorated());
        $this->assertFalse($output->isQuiet());

        $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        $output->setDecorated(true);

        $this->assertSame(OutputInterface::VERBOSITY_NORMAL, $output->getVerbosity());
        $this->assertFalse($output->isDecorated());
    }
}

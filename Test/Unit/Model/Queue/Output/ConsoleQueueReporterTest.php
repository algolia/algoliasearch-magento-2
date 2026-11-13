<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue\Output;

use Algolia\AlgoliaSearch\Model\Queue\Output\ConsoleQueueReporter;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleQueueReporterTest extends TestCase
{
    public function testInfoIsWrittenWithInfoDecoration(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('writeln')->with('<info>all good</info>');

        (new ConsoleQueueReporter($output))->info('all good');
    }

    public function testWarningIsWrittenWithCommentDecoration(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('writeln')->with('<comment>heads up</comment>');

        (new ConsoleQueueReporter($output))->warning('heads up');
    }

    public function testErrorIsWrittenWithErrorDecoration(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('writeln')->with('<error>something broke</error>');

        (new ConsoleQueueReporter($output))->error('something broke');
    }

    public function testLogIsWrittenWithoutAnyDecoration(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('writeln')->with('plain line');

        (new ConsoleQueueReporter($output))->log('plain line');
    }
}

<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue\Output;

use Algolia\AlgoliaSearch\Model\Queue\Output\ConsoleQueueReporter;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
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

    public function testLogIsWrittenRawWithoutAnyDecoration(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('writeln')->with('plain line', OutputInterface::OUTPUT_RAW);

        (new ConsoleQueueReporter($output))->log('plain line');
    }

    public function testLogPassesRelayedWorkerTextThroughWithoutInterpretingFormatterTags(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with('[Store 2 (English)] <bg=yellow;options=bold>raw child text</>', OutputInterface::OUTPUT_RAW);

        (new ConsoleQueueReporter($output))->log('[Store 2 (English)] <bg=yellow;options=bold>raw child text</>');
    }

    public function testLogDoesNotThrowOnMismatchedFormatterTags(): void
    {
        $output = new BufferedOutput();
        $message = '[Store 1 (English)] Failed: <error>Store 1</info>';

        (new ConsoleQueueReporter($output))->log($message);

        $this->assertSame($message . PHP_EOL, $output->fetch());
    }

    public function testMismatchedFormatterTagsThrowWhenFormatted(): void
    {
        // Documents the hazard log() guards against: the same text written through the formatter
        // (without OUTPUT_RAW) throws from Symfony's style stack.
        $output = new BufferedOutput();
        $message = '[Store 1 (English)] Failed: <error>Store 1</info>';

        $this->expectException(\InvalidArgumentException::class);

        $output->setDecorated(false);
        $output->write($message, true);
    }
}

<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue\Output;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Model\Queue\Output\ConsoleQueueReporter;
use Algolia\AlgoliaSearch\Model\Queue\Output\StorePrefixedOutput;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class StorePrefixedOutputTest extends TestCase
{
    private const string LABEL = 'Store 2 (English)';

    private function createObjectToTest(QueueReporterInterface $reporter, string $label = self::LABEL): StorePrefixedOutput
    {
        return new StorePrefixedOutput($reporter, $label);
    }

    private function createReporter(BufferedOutput $output): ConsoleQueueReporter
    {
        return new ConsoleQueueReporter($output);
    }

    public function testPrefixesCompleteLinesAndKeepsTheNewlineFraming(): void
    {
        $output = new BufferedOutput();

        $this->createObjectToTest($this->createReporter($output))->write("first line\nsecond line\n");

        $this->assertSame(
            '[Store 2 (English)] first line' . "\n" . '[Store 2 (English)] second line' . "\n",
            $output->fetch()
        );
    }

    public function testBuffersPartialChunksUntilTheLineCompletes(): void
    {
        $output = new BufferedOutput();
        $prefixed = $this->createObjectToTest($this->createReporter($output));

        $prefixed->write('hel');
        $prefixed->write('lo wor');
        $this->assertSame('', $output->fetch());

        $prefixed->write("ld\n");

        $this->assertSame('[Store 2 (English)] hello world' . "\n", $output->fetch());
    }

    public function testKeepsTheBufferAcrossManyPartialChunks(): void
    {
        $output = new BufferedOutput();
        $prefixed = $this->createObjectToTest($this->createReporter($output));

        foreach (str_split('a progressive line without a break yet') as $chunk) {
            $prefixed->write($chunk);
        }

        $this->assertSame('', $output->fetch());

        $prefixed->write("\n");

        $this->assertSame(
            '[Store 2 (English)] a progressive line without a break yet' . "\n",
            $output->fetch()
        );
    }

    public function testStripsCarriageReturnsFromCrlfLineEndings(): void
    {
        $output = new BufferedOutput();

        $this->createObjectToTest($this->createReporter($output))->write("first\r\nsecond\r\n");

        $this->assertSame(
            '[Store 2 (English)] first' . "\n" . '[Store 2 (English)] second' . "\n",
            $output->fetch()
        );
    }

    public function testHandlesCrlfSplitAcrossChunks(): void
    {
        $output = new BufferedOutput();
        $prefixed = $this->createObjectToTest($this->createReporter($output));

        $prefixed->write("first\r");
        $prefixed->write("\nsecond");

        $this->assertSame('[Store 2 (English)] first' . "\n", $output->fetch());

        $prefixed->flushTail();

        $this->assertSame('[Store 2 (English)] second' . "\n", $output->fetch());
    }

    public function testEmitsMultipleLinesFromASingleChunk(): void
    {
        $output = new BufferedOutput();

        $this->createObjectToTest($this->createReporter($output))->write("one\ntwo\nthree\n");

        $this->assertSame(
            '[Store 2 (English)] one' . "\n"
            . '[Store 2 (English)] two' . "\n"
            . '[Store 2 (English)] three' . "\n",
            $output->fetch()
        );
    }

    public function testFlushTailEmitsAPartialLineWithoutATrailingNewline(): void
    {
        $output = new BufferedOutput();
        $prefixed = $this->createObjectToTest($this->createReporter($output));

        $prefixed->write('dying words');

        $this->assertSame('', $output->fetch());

        $prefixed->flushTail();

        $this->assertSame('[Store 2 (English)] dying words' . "\n", $output->fetch());
    }

    public function testFlushTailWritesNothingWhenTheBufferIsEmpty(): void
    {
        $output = new BufferedOutput();
        $prefixed = $this->createObjectToTest($this->createReporter($output));

        $prefixed->write("complete line\n");
        $output->fetch();

        $prefixed->flushTail();

        $this->assertSame('', $output->fetch());
    }

    public function testFlushTailEmitsOnlyTheRemainingTail(): void
    {
        $output = new BufferedOutput();
        $prefixed = $this->createObjectToTest($this->createReporter($output));

        $prefixed->write("complete\npart");
        $prefixed->flushTail();

        $this->assertSame(
            '[Store 2 (English)] complete' . "\n" . '[Store 2 (English)] part' . "\n",
            $output->fetch()
        );
    }

    public function testWritelnPrefixesAndTerminatesTheLine(): void
    {
        $output = new BufferedOutput();

        $this->createObjectToTest($this->createReporter($output))->writeln('a whole line');

        $this->assertSame('[Store 2 (English)] a whole line' . "\n", $output->fetch());
    }

    public function testWriteWithNewlineFlagTerminatesTheLine(): void
    {
        $output = new BufferedOutput();

        $this->createObjectToTest($this->createReporter($output))->write('a whole line', true);

        $this->assertSame('[Store 2 (English)] a whole line' . "\n", $output->fetch());
    }
}

<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue\Output;

use Algolia\AlgoliaSearch\Model\Queue\Output\StorePrefixedOutput;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class StorePrefixedOutputTest extends TestCase
{
    private const string LABEL = 'Store 2 (English)';

    private function createObjectToTest(OutputInterface $inner, string $label = self::LABEL): StorePrefixedOutput
    {
        return new StorePrefixedOutput($inner, $label);
    }

    private function createInnerOutput(): BufferedOutput
    {
        return new BufferedOutput();
    }

    public function testPrefixesCompleteLinesAndKeepsTheNewlineFraming(): void
    {
        $inner = $this->createInnerOutput();

        $this->createObjectToTest($inner)->write("first line\nsecond line\n");

        $this->assertSame(
            '[Store 2 (English)] first line' . "\n" . '[Store 2 (English)] second line' . "\n",
            $inner->fetch()
        );
    }

    public function testBuffersPartialChunksUntilTheLineCompletes(): void
    {
        $inner = $this->createInnerOutput();
        $output = $this->createObjectToTest($inner);

        $output->write('hel');
        $output->write('lo wor');
        $this->assertSame('', $inner->fetch());

        $output->write("ld\n");

        $this->assertSame('[Store 2 (English)] hello world' . "\n", $inner->fetch());
    }

    public function testKeepsTheBufferAcrossManyPartialChunks(): void
    {
        $inner = $this->createInnerOutput();
        $output = $this->createObjectToTest($inner);

        foreach (str_split('a progressive line without a break yet') as $chunk) {
            $output->write($chunk);
        }

        $this->assertSame('', $inner->fetch());

        $output->write("\n");

        $this->assertSame(
            '[Store 2 (English)] a progressive line without a break yet' . "\n",
            $inner->fetch()
        );
    }

    public function testStripsCarriageReturnsFromCrlfLineEndings(): void
    {
        $inner = $this->createInnerOutput();

        $this->createObjectToTest($inner)->write("first\r\nsecond\r\n");

        $this->assertSame(
            '[Store 2 (English)] first' . "\n" . '[Store 2 (English)] second' . "\n",
            $inner->fetch()
        );
    }

    public function testHandlesCrlfSplitAcrossChunks(): void
    {
        $inner = $this->createInnerOutput();
        $output = $this->createObjectToTest($inner);

        $output->write("first\r");
        $output->write("\nsecond");

        $this->assertSame('[Store 2 (English)] first' . "\n", $inner->fetch());

        $output->flushTail();

        $this->assertSame('[Store 2 (English)] second' . "\n", $inner->fetch());
    }

    public function testEmitsMultipleLinesFromASingleChunk(): void
    {
        $inner = $this->createInnerOutput();

        $this->createObjectToTest($inner)->write("one\ntwo\nthree\n");

        $this->assertSame(
            '[Store 2 (English)] one' . "\n"
            . '[Store 2 (English)] two' . "\n"
            . '[Store 2 (English)] three' . "\n",
            $inner->fetch()
        );
    }

    public function testFlushTailEmitsAPartialLineWithoutATrailingNewline(): void
    {
        $inner = $this->createInnerOutput();
        $output = $this->createObjectToTest($inner);

        $output->write('dying words');

        $this->assertSame('', $inner->fetch());

        $output->flushTail();

        $this->assertSame('[Store 2 (English)] dying words' . "\n", $inner->fetch());
    }

    public function testFlushTailWritesNothingWhenTheBufferIsEmpty(): void
    {
        $inner = $this->createInnerOutput();
        $output = $this->createObjectToTest($inner);

        $output->write("complete line\n");
        $inner->fetch();

        $output->flushTail();

        $this->assertSame('', $inner->fetch());
    }

    public function testFlushTailEmitsOnlyTheRemainingTail(): void
    {
        $inner = $this->createInnerOutput();
        $output = $this->createObjectToTest($inner);

        $output->write("complete\npart");
        $output->flushTail();

        $this->assertSame(
            '[Store 2 (English)] complete' . "\n" . '[Store 2 (English)] part' . "\n",
            $inner->fetch()
        );
    }

    public function testWritelnPrefixesAndTerminatesTheLine(): void
    {
        $inner = $this->createInnerOutput();

        $this->createObjectToTest($inner)->writeln('a whole line');

        $this->assertSame('[Store 2 (English)] a whole line' . "\n", $inner->fetch());
    }

    public function testWriteWithNewlineFlagTerminatesTheLine(): void
    {
        $inner = $this->createInnerOutput();

        $this->createObjectToTest($inner)->write('a whole line', true);

        $this->assertSame('[Store 2 (English)] a whole line' . "\n", $inner->fetch());
    }

    public function testWriteAcceptsIterablesOfMessages(): void
    {
        $inner = $this->createInnerOutput();

        $this->createObjectToTest($inner)->write(['first' . "\n", 'second' . "\n"]);

        $this->assertSame(
            '[Store 2 (English)] first' . "\n" . '[Store 2 (English)] second' . "\n",
            $inner->fetch()
        );
    }

    public function testVerbosityAndDecorationFallBackToDefaults(): void
    {
        $output = $this->createObjectToTest($this->createInnerOutput());

        $this->assertSame(OutputInterface::VERBOSITY_NORMAL, $output->getVerbosity());
        $this->assertFalse($output->isDecorated());

        $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        $output->setDecorated(true);

        $this->assertSame(OutputInterface::VERBOSITY_NORMAL, $output->getVerbosity());
        $this->assertFalse($output->isDecorated());
    }

    public function testFormatterCallsAreForwardedToTheInnerOutput(): void
    {
        $formatter = $this->createStub(OutputFormatterInterface::class);
        $inner = $this->createStub(OutputInterface::class);
        $inner->method('getFormatter')->willReturn($formatter);

        $output = $this->createObjectToTest($inner);
        $output->setFormatter($formatter);

        $this->assertSame($formatter, $output->getFormatter());
    }
}

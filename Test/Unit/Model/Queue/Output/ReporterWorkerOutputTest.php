<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue\Output;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Model\Queue\Output\ReporterWorkerOutput;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

class ReporterWorkerOutputTest extends TestCase
{
    private function createObjectToTest(QueueReporterInterface $reporter): ReporterWorkerOutput
    {
        return new ReporterWorkerOutput($reporter);
    }

    public function testWritelnForwardsTheLineToTheReporterLog(): void
    {
        $reporter = $this->createMock(QueueReporterInterface::class);
        $reporter->expects($this->once())->method('log')->with('[Store 2 (English)] worker line');

        $this->createObjectToTest($reporter)->writeln('[Store 2 (English)] worker line');
    }

    public function testWriteForwardsTheMessageToTheReporterLog(): void
    {
        $reporter = $this->createMock(QueueReporterInterface::class);
        $reporter->expects($this->once())->method('log')->with('chunk');

        $this->createObjectToTest($reporter)->write('chunk');
    }

    public function testEachMessageOfAnIterableIsForwardedSeparately(): void
    {
        $logged = [];
        $reporter = $this->createMock(QueueReporterInterface::class);
        $reporter->expects($this->exactly(2))
            ->method('log')
            ->willReturnCallback(function (string $message) use (&$logged) {
                $logged[] = $message;
            });

        $this->createObjectToTest($reporter)->writeln(['first line', 'second line']);

        $this->assertSame(['first line', 'second line'], $logged);
    }

    public function testWriteWithNewlineFlagStillForwardsTheBareMessage(): void
    {
        $reporter = $this->createMock(QueueReporterInterface::class);
        $reporter->expects($this->once())->method('log')->with('a whole line');

        $this->createObjectToTest($reporter)->write('a whole line', true);
    }

    public function testVerbosityAndDecorationFallBackToDefaults(): void
    {
        $output = $this->createObjectToTest($this->createStub(QueueReporterInterface::class));

        $this->assertSame(OutputInterface::VERBOSITY_NORMAL, $output->getVerbosity());
        $this->assertFalse($output->isDecorated());

        $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        $output->setDecorated(true);

        $this->assertSame(OutputInterface::VERBOSITY_NORMAL, $output->getVerbosity());
        $this->assertFalse($output->isDecorated());
    }
}

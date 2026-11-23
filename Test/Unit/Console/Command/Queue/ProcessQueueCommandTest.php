<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Console\Command\Queue;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Console\Command\Queue\ProcessQueueCommand;
use Algolia\AlgoliaSearch\Model\Queue\MaxProcessesResolver;
use Algolia\AlgoliaSearch\Model\Queue\Output\ConsoleQueueReporter;
use Algolia\AlgoliaSearch\Model\Queue\Output\ConsoleQueueReporterFactory;
use Algolia\AlgoliaSearch\Service\Queue\QueueOrchestrator;
use Algolia\AlgoliaSearch\Service\Queue\QueueWorker;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Command concerns only: argument/option parsing, mode selection, and delegation to
 * QueueWorker and QueueOrchestrator.
 */
class ProcessQueueCommandTest extends TestCase
{
    protected function createObjectToTest(
        ?StoreNameFetcher $storeNameFetcher = null,
        ?MaxProcessesResolver $maxProcessesResolver = null,
        ?QueueOrchestrator $queueOrchestrator = null,
        ?QueueWorker $queueWorker = null,
        ?ConsoleQueueReporterFactory $consoleQueueReporterFactory = null,
        bool $spawningSupported = true,
    ): ProcessQueueCommand {
        $maxProcessesResolver ??= $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('isProcessSpawningSupported')->willReturn($spawningSupported);

        $queueOrchestrator ??= $this->createStub(QueueOrchestrator::class);
        $queueOrchestrator->method('runSequential')->willReturn(true);
        $queueOrchestrator->method('orchestrate')->willReturn(true);

        $queueWorker ??= $this->createStub(QueueWorker::class);
        $queueWorker->method('processStores')->willReturn(true);

        $consoleQueueReporterFactory ??= $this->createConsoleQueueReporterFactory();

        return new ProcessQueueCommand(
            $this->createStub(State::class),
            $storeNameFetcher ?? $this->createStub(StoreNameFetcher::class),
            $maxProcessesResolver,
            $queueOrchestrator,
            $queueWorker,
            $consoleQueueReporterFactory,
        );
    }

    private function createConsoleQueueReporterFactory(): ConsoleQueueReporterFactory
    {
        $factory = $this->createStub(ConsoleQueueReporterFactory::class);
        $factory->method('create')->willReturnCallback(
            fn (array $data = []) => new ConsoleQueueReporter($data['output'])
        );

        return $factory;
    }

    public function testExecuteDelegatesWorkerModeToQueueWorker(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->expects($this->once())->method('resolveStoreNames')->with([2, 3], true);

        $capturedArgs = [];
        $queueWorker = $this->createMock(QueueWorker::class);
        $queueWorker->expects($this->once())
            ->method('processStores')
            ->willReturnCallback(function (...$args) use (&$capturedArgs) {
                $capturedArgs = $args;

                return false;
            });

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(storeNameFetcher: $storeNameFetcher, queueWorker: $queueWorker),
            [2, 3],
            $output,
            ['--run-id' => 'abc123run']
        );

        $this->assertSame(
            Cli::RETURN_FAILURE,
            $code,
            'A worker run reporting failure must surface as a failing exit code'
        );
        $this->assertSame([2, 3], $capturedArgs[0]);
        $this->assertInstanceOf(ConsoleQueueReporter::class, $capturedArgs[1]);
        $this->assertSame('abc123run', $capturedArgs[2]);
        // The worker's own lines must flow back to the command's output through the reporter
        $capturedArgs[1]->log('reporter marker');
        $this->assertStringContainsString('reporter marker', $output->fetch());
    }

    public function testExecuteThreadsRunIdOptionInWorkerMode(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('resolveStoreNames')->with([2], true);

        $queueWorker = $this->createMock(QueueWorker::class);
        $queueWorker->expects($this->once())
            ->method('processStores')
            ->with([2], $this->isInstanceOf(QueueReporterInterface::class), 'abc123run')
            ->willReturn(true);

        $code = $this->executeCommand(
            $this->createObjectToTest(storeNameFetcher: $storeNameFetcher, queueWorker: $queueWorker),
            [2],
            null,
            ['--run-id' => 'abc123run']
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    public function testExecuteDelegatesNullRunIdWhenOptionIsAbsent(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('resolveStoreNames')->with([2], true);

        $capturedArgs = [];
        $queueWorker = $this->createMock(QueueWorker::class);
        $queueWorker->expects($this->once())
            ->method('processStores')
            ->willReturnCallback(function (...$args) use (&$capturedArgs) {
                $capturedArgs = $args;

                return true;
            });

        $code = $this->executeCommand(
            $this->createObjectToTest(storeNameFetcher: $storeNameFetcher, queueWorker: $queueWorker),
            [2]
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertNull($capturedArgs[2]); //only care about null for this test
    }

    public function testExecuteRunsWorkerModeEvenWithSequentialOption(): void
    {
        $storeNameFetcher = $this->createStub(StoreNameFetcher::class);

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->never())->method('runSequential');

        $queueWorker = $this->createMock(QueueWorker::class);
        // Worker mode (via store ID) trumps other options
        $queueWorker->expects($this->once())->method('processStores')->willReturn(true);

        $code = $this->executeCommand(
            $this->createObjectToTest(
                storeNameFetcher: $storeNameFetcher,
                queueOrchestrator: $queueOrchestrator,
                queueWorker: $queueWorker
            ),
            [2],
            new BufferedOutput(),
            ['--sequential' => true]
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    #[DataProvider('invalidStoreIdsProvider')]
    public function testExecuteFailsOnInvalidStoreId(string|int $storeId): void
    {
        $queueWorker = $this->createMock(QueueWorker::class);
        $queueWorker->expects($this->never())->method('processStores');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(queueWorker: $queueWorker),
            [$storeId],
            $output
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString('Store ID argument must be an integer', $output->fetch());
    }

    public static function invalidStoreIdsProvider(): array
    {
        return [
            'letters' => ['abc'],
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    public function testExecuteFailsOnUnknownStoreBeforeDelegatingToTheWorker(): void
    {
        $storeNameFetcher = $this->createStub(StoreNameFetcher::class);
        $storeNameFetcher->method('resolveStoreNames')
            ->willThrowException(NoSuchEntityException::singleField('storeId', 42));

        $queueWorker = $this->createMock(QueueWorker::class);
        $queueWorker->expects($this->never())->method('processStores');

        $code = $this->executeCommand(
            $this->createObjectToTest(storeNameFetcher: $storeNameFetcher, queueWorker: $queueWorker),
            [42]
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
    }

    public function testExecuteWithoutStoreIdsRunsSequentialPath(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(1);

        $queueWorker = $this->createMock(QueueWorker::class);
        $queueWorker->expects($this->never())->method('processStores');

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(true);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator,
                queueWorker: $queueWorker
            ),
            []
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    public function testExecutePropagatesSequentialFailure(): void
    {
        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())
            ->method('runSequential')
            ->willReturn(false);

        $code = $this->executeCommand(
            $this->createObjectToTest(queueOrchestrator: $queueOrchestrator),
            []
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
    }

    public function testDefinitionKeepsAliasAndOptionalArrayStoreArgument(): void
    {
        $cmd = $this->createObjectToTest();
        $this->invokeMethod($cmd, 'configure');

        $this->assertContains('algolia:reindex:process_queue', $cmd->getAliases());

        $argument = $cmd->getDefinition()->getArgument('store_id');
        $this->assertTrue($argument->isArray());
        $this->assertFalse($argument->isRequired());
        $this->assertTrue($cmd->getDefinition()->hasOption('max-processes'));
        $this->assertTrue($cmd->getDefinition()->hasOption('sequential'));
        $this->assertTrue($cmd->getDefinition()->hasOption('run-id'));
    }

    public function testSequentialOptionForcesSequentialPathEvenWithMaxProcesses(): void
    {
        $maxProcessesResolver = $this->createMock(MaxProcessesResolver::class);
        $maxProcessesResolver->expects($this->never())->method('resolve');

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(true);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator
            ),
            [],
            $output,
            ['--sequential' => true, '--max-processes' => '4']
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('takes precedence', $output->fetch());
    }

    public function testExecuteFallsBackToSequentialWhenResolvedMaxProcessesIsOne(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(1);

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(true);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator
            ),
            []
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    public function testExecuteFallsBackToSequentialWhenMaxProcessesOptionIsOne(): void
    {
        $maxProcessesResolver = $this->createMock(MaxProcessesResolver::class);
        $maxProcessesResolver->expects($this->once())->method('resolve')->with(1)->willReturn(1);

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(true);

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator
            ),
            [],
            $output,
            ['--max-processes' => '1']
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('Maximum parallel queue processes: 1', $output->fetch());
    }

    public function testExecuteFallsBackToSequentialWhenProcessSpawningIsUnsupported(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(4);

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(true);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator,
                spawningSupported: false
            ),
            [],
            $output
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('proc_open is disabled', $output->fetch());
    }

    public function testExecuteDelegatesOrchestrationWithOverrideAndOutput(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(4);

        $output = new BufferedOutput();
        $capturedArgs = [];
        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())
            ->method('orchestrate')
            ->willReturnCallback(function (...$args) use (&$capturedArgs) {
                $capturedArgs = $args;

                return true;
            });

        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator
            ),
            [],
            $output,
            ['--max-processes' => '4']
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertSame(4, $capturedArgs[0]);
        $this->assertInstanceOf(ConsoleQueueReporter::class, $capturedArgs[1]);
        // The orchestrator's own lines must flow back to the command's output through the reporter
        $capturedArgs[1]->log('reporter marker');
        $this->assertStringContainsString('reporter marker', $output->fetch());
    }

    public function testExecutePropagatesOrchestratorExceptions(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(2);

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())
            ->method('orchestrate')
            ->willThrowException(new \RuntimeException('spawn failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('spawn failed');

        $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator
            ),
            []
        );
    }

    public function testExecutePassesMaxProcessesOptionToResolverAndPrintsItsResult(): void
    {
        // The stub returns 7 intentionally (a value the command cannot derive itself) so the
        // assertion proves the printed value came from the *resolver*, not any option passed.
        // Spawning is disabled for simplicity (resolver still consulted for mode decision).
        $maxProcessesResolver = $this->createMock(MaxProcessesResolver::class);
        $maxProcessesResolver->expects($this->once())
            ->method('resolve')
            ->with(4)
            ->willReturn(7);

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(true);

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator,
                spawningSupported: false
            ),
            [],
            $output,
            ['--max-processes' => '4']
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString(
            'Maximum parallel queue processes: 7',
            $output->fetch(),
            'Output should show the resolver\'s return value (7), not the raw --max-processes option (4)'
        );
    }

    /**
     * The resolver is what decides the mode in the no-arg branch: even without the
     * --max-processes option it must be consulted (admin config / auto-detection).
     */
    public function testExecuteResolvesMaxProcessesWithoutOptionInNoArgMode(): void
    {
        $maxProcessesResolver = $this->createMock(MaxProcessesResolver::class);
        $maxProcessesResolver->expects($this->once())
            ->method('resolve')
            ->with(null)
            ->willReturn(1);

        $code = $this->executeCommand(
            $this->createObjectToTest(maxProcessesResolver: $maxProcessesResolver),
            []
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    #[DataProvider('invalidMaxProcessesProvider')]
    public function testExecuteFailsOnInvalidMaxProcessesOption(string $value): void
    {
        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->never())->method('orchestrate');
        $queueOrchestrator->expects($this->never())->method('runSequential');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(queueOrchestrator: $queueOrchestrator),
            [],
            $output,
            ['--max-processes' => $value]
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString('must be an integer greater than or equal to 1', $output->fetch());
    }

    public static function invalidMaxProcessesProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-2'],
            'non numeric' => ['many'],
            'decimal' => ['2.5'],
        ];
    }

    private function executeCommand(
        ProcessQueueCommand $cmd,
        array $storeIds,
        ?BufferedOutput $output = null,
        array $options = []
    ): int {
        $this->invokeMethod($cmd, 'configure');

        $input = new ArrayInput(array_merge($options, ['store_id' => $storeIds]));
        $input->bind($cmd->getDefinition());

        return $this->invokeMethod($cmd, 'execute', [
            $input,
            $output ?? new BufferedOutput(),
        ]);
    }
}
